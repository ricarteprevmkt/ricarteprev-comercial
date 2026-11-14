<?php

namespace App\Actions\Referrals;

use App\Enums\OpportunityStage;
use App\Enums\ReferralStatus;
use App\Enums\VisitStatus;
use App\Models\Lead;
use App\Models\Referral;
use App\Models\ReferralHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mantém a situação das indicações de acordo com o que acontece no funil.
 *
 * Para cada indicação válida (não duplicada) do contato, considera as
 * oportunidades abertas a partir da data da indicação:
 * - a etapa mais avançada alcançada define a situação (nunca retrocede);
 * - se todas foram perdidas/desqualificadas e nenhuma assinou, fica "Perdida";
 * - sem oportunidade, uma visita concluída conta como "Contato realizado".
 */
final class SyncReferralStatus
{
    public function forLead(Lead $lead): void
    {
        $referrals = Referral::query()->where('lead_id', $lead->id)->where('is_duplicate', false)->get();

        foreach ($referrals as $referral) {
            $this->sync($referral);
        }
    }

    public function sync(Referral $referral): void
    {
        $since = $referral->referred_at->startOfDay();
        $opportunities = $referral->lead->opportunities()->where('created_at', '>=', $since)->get();

        $rank = $referral->furthest_rank;
        $target = null;

        if ($opportunities->isNotEmpty()) {
            $best = (int) $opportunities->max('furthest_stage_rank');
            $mapped = ReferralStatus::fromOpportunityStage(OpportunityStage::fromRank(max(1, $best)));
            $rank = max($rank, $mapped?->rank() ?? 1);

            $allClosedLost = $opportunities->every(fn ($o) => in_array($o->stage, [OpportunityStage::Lost, OpportunityStage::Disqualified], true));
            $target = $allClosedLost ? ReferralStatus::Lost : ReferralStatus::fromRank($rank);
        } elseif ($referral->lead->visits()->where('status', VisitStatus::Done->value)->where('scheduled_at', '>=', $since)->exists()) {
            $rank = max($rank, (int) ReferralStatus::Contacted->rank());
            $target = $referral->status === ReferralStatus::Lost ? null : ReferralStatus::fromRank($rank);
        }

        if ($target !== null && ($target !== $referral->status || $rank !== $referral->furthest_rank)) {
            $this->apply($referral, $target, $rank, null, 'Atualizado pelo funil');
        }
    }

    /**
     * Mudança manual, feita pela equipe (ex.: contato realizado por telefone, ou perdida).
     */
    public function manual(Referral $referral, ReferralStatus $to, User $user, ?string $note): void
    {
        if ($referral->is_duplicate) {
            throw ValidationException::withMessages(['status' => 'Indicações duplicadas não mudam de situação.']);
        }

        if ($to === ReferralStatus::Signed) {
            throw ValidationException::withMessages(['status' => '"Contrato assinado" vem do registro do contrato no funil.']);
        }

        if ($to === $referral->status) {
            throw ValidationException::withMessages(['status' => 'A indicação já está nesta situação.']);
        }

        $rank = max($referral->furthest_rank, $to->rank() ?? 0);
        $this->apply($referral, $to, $rank, $user, $note);
    }

    private function apply(Referral $referral, ReferralStatus $to, int $rank, ?User $user, ?string $note): void
    {
        DB::transaction(function () use ($referral, $to, $rank, $user, $note) {
            $from = $referral->status;
            $referral->forceFill(['status' => $to, 'furthest_rank' => $rank])->save();

            if ($from !== $to) {
                ReferralHistory::query()->create([
                    'referral_id' => $referral->id,
                    'from_status' => $from,
                    'to_status' => $to,
                    'user_id' => $user?->id,
                    'note' => $note,
                    'created_at' => now(),
                ]);
            }
        });
    }
}
