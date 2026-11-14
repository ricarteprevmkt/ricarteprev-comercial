<?php

namespace App\Actions\Crm;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra uma proposta. Se a oportunidade ainda não chegou a "Proposta
 * apresentada", ela avança para essa etapa automaticamente.
 */
final class RegisterProposal
{
    public function __construct(private MoveOpportunity $move) {}

    /**
     * @param  array{presented_at: string, amount?: string|null, description?: string|null}  $data
     */
    public function handle(Opportunity $opportunity, array $data, ?User $user): Proposal
    {
        if ($opportunity->stage->isClosed()) {
            throw ValidationException::withMessages(['proposal.presented_at' => 'Reabra a oportunidade antes de registrar uma proposta.']);
        }

        return DB::transaction(function () use ($opportunity, $data, $user) {
            $proposal = Proposal::query()->create([
                'opportunity_id' => $opportunity->id,
                'presented_at' => $data['presented_at'],
                'amount' => $data['amount'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $user?->id,
            ]);

            if (($opportunity->stage->rank() ?? 0) < (int) OpportunityStage::Proposal->rank()) {
                $this->move->handle($opportunity, OpportunityStage::Proposal, $user, 'Proposta registrada');
            } else {
                $opportunity->forceFill(['last_activity_at' => now()])->save();
            }

            return $proposal;
        });
    }
}
