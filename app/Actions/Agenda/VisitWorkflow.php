<?php

namespace App\Actions\Agenda;

use App\Actions\Crm\SaveLead;
use App\Enums\OpportunityStage;
use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mudanças de situação de uma visita: confirmar, iniciar (check-in),
 * concluir com resultado, não realizada, cancelar e reagendar.
 *
 * Concluir uma visita ligada a uma oportunidade atualiza a oportunidade:
 * registra a atividade e, se houver, a próxima ação e o prazo.
 */
final class VisitWorkflow
{
    public function __construct(private SaveVisit $saveVisit, private SaveLead $saveLead) {}

    public function confirm(Visit $visit): Visit
    {
        $this->ensure($visit, [VisitStatus::Scheduled]);
        $visit->update(['status' => VisitStatus::Confirmed]);

        return $visit;
    }

    /** Check-in: a posição é a informada pelo navegador, com consentimento. Nunca é simulada. */
    public function start(Visit $visit, ?float $lat = null, ?float $lng = null): Visit
    {
        $this->ensure($visit, [VisitStatus::Scheduled, VisitStatus::Confirmed]);

        $visit->update([
            'status' => VisitStatus::InProgress,
            'started_at' => now(),
            'checkin_latitude' => $lat,
            'checkin_longitude' => $lng,
            'checkin_at' => $lat !== null && $lng !== null ? now() : null,
        ]);

        return $visit;
    }

    /**
     * @param  array{started_at: string, finished_at: string, outcome: string, next_action?: string|null,
     *     next_contact_at?: string|null, km_reported?: string|null, open_opportunity?: bool}  $data
     */
    public function complete(Visit $visit, array $data, ?User $user): Visit
    {
        $this->ensure($visit, [VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress]);

        if (CarbonImmutable::parse($data['finished_at'])->lt(CarbonImmutable::parse($data['started_at']))) {
            throw ValidationException::withMessages(['result.finished_at' => 'O horário de término não pode ser antes do início.']);
        }

        return DB::transaction(function () use ($visit, $data, $user) {
            $visit->update([
                'status' => VisitStatus::Done,
                'started_at' => $data['started_at'],
                'finished_at' => $data['finished_at'],
                'outcome' => $data['outcome'],
                'next_action' => $data['next_action'] ?? null,
                'next_contact_at' => $data['next_contact_at'] ?? null,
                'km_reported' => $data['km_reported'] ?? null,
            ]);

            $opportunity = $visit->opportunity;

            if (! $opportunity && ($data['open_opportunity'] ?? false) && $visit->lead) {
                $opportunity = $this->saveLead->openOpportunity($visit->lead, [
                    'stage' => OpportunityStage::Contact,
                    'next_action' => $data['next_action'] ?? null,
                    'next_action_due' => $data['next_contact_at'] ?? null,
                ], $user);
                $opportunity->forceFill(['visit_id' => $visit->id])->save();
                $visit->update(['opportunity_id' => $opportunity->id]);
            } elseif ($opportunity && ! $opportunity->stage->isClosed()) {
                $opportunity->forceFill(array_filter([
                    'last_activity_at' => now(),
                    'next_action' => $data['next_action'] ?? null,
                    'next_action_due' => $data['next_contact_at'] ?? null,
                ], fn ($v) => $v !== null))->save();
            }

            return $visit;
        });
    }

    public function notDone(Visit $visit, int $reasonId, ?string $note): Visit
    {
        $this->ensure($visit, [VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress]);
        $visit->update(['status' => VisitStatus::NotDone, 'not_done_reason_id' => $reasonId, 'outcome' => $note]);

        return $visit;
    }

    public function cancel(Visit $visit, ?string $note): Visit
    {
        $this->ensure($visit, [VisitStatus::Scheduled, VisitStatus::Confirmed]);

        $visit->update(['status' => VisitStatus::Cancelled, 'outcome' => $note]);
        $this->saveVisit->rebuild($visit->executive_id, $visit->scheduled_at);

        return $visit;
    }

    /**
     * Cria uma nova visita na nova data e marca a original como reagendada,
     * mantendo o vínculo entre as duas.
     */
    public function reschedule(Visit $visit, string $newDateTime, ?string $note, ?User $user): Visit
    {
        $this->ensure($visit, [VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress, VisitStatus::NotDone]);

        return DB::transaction(function () use ($visit, $newDateTime, $note, $user) {
            $new = $visit->replicate(['status', 'started_at', 'finished_at', 'outcome', 'not_done_reason_id', 'checkin_latitude', 'checkin_longitude', 'checkin_at', 'rescheduled_to_id', 'km_reported']);
            $new->status = VisitStatus::Scheduled;
            $new->scheduled_at = CarbonImmutable::parse($newDateTime);
            $new->created_by = $user?->id;
            $new->save();

            $visit->update([
                'status' => $visit->status === VisitStatus::NotDone ? VisitStatus::NotDone : VisitStatus::Rescheduled,
                'rescheduled_to_id' => $new->id,
                'outcome' => $note ?? $visit->outcome,
            ]);

            $this->saveVisit->rebuild($visit->executive_id, $visit->scheduled_at);
            $this->saveVisit->rebuild($new->executive_id, $new->scheduled_at);

            return $new;
        });
    }

    /** @param list<VisitStatus> $allowed */
    private function ensure(Visit $visit, array $allowed): void
    {
        if (! in_array($visit->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Não é possível fazer isso com uma visita {$visit->status->label()}."]);
        }
    }
}
