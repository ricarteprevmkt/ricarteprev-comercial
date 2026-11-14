<?php

namespace App\Actions\Crm;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\OpportunityHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Muda a etapa de uma oportunidade e registra o histórico.
 *
 * Regras:
 * - "Contrato assinado" só é alcançado pelo registro do contrato (internal = true).
 * - Oportunidade com contrato assinado não muda de etapa por aqui.
 * - Perdido e Desqualificado exigem motivo.
 * - A etapa mais avançada já alcançada nunca diminui (base das conversões).
 */
final class MoveOpportunity
{
    public function handle(
        Opportunity $opportunity,
        OpportunityStage $to,
        ?User $user,
        ?string $note = null,
        ?int $lossReasonId = null,
        bool $internal = false,
    ): Opportunity {
        $from = $opportunity->stage;

        if ($from === $to) {
            throw ValidationException::withMessages(['stage' => 'A oportunidade já está nesta etapa.']);
        }

        if ($to === OpportunityStage::Signed && ! $internal) {
            throw ValidationException::withMessages(['stage' => 'Para marcar como contrato assinado, registre o contrato.']);
        }

        if ($from === OpportunityStage::Signed && ! $internal) {
            throw ValidationException::withMessages(['stage' => 'Esta oportunidade já tem contrato assinado e não pode mudar de etapa.']);
        }

        if ($to->requiresReason() && $lossReasonId === null) {
            throw ValidationException::withMessages(['lossReasonId' => 'Informe o motivo do encerramento.']);
        }

        DB::transaction(function () use ($opportunity, $from, $to, $user, $note, $lossReasonId) {
            $now = now();

            $opportunity->stage = $to;
            $opportunity->stage_changed_at = $now;
            $opportunity->last_activity_at = $now;
            $opportunity->furthest_stage_rank = max($opportunity->furthest_stage_rank, $to->rank() ?? 0);
            $opportunity->closed_at = $to->isClosed() ? $now : null;
            $opportunity->loss_reason_id = $to->requiresReason() ? $lossReasonId : null;
            $opportunity->loss_note = $to->requiresReason() ? $note : null;
            $opportunity->save();

            OpportunityHistory::query()->create([
                'opportunity_id' => $opportunity->id,
                'from_stage' => $from,
                'to_stage' => $to,
                'user_id' => $user?->id,
                'note' => $note,
                'created_at' => $now,
            ]);
        });

        return $opportunity;
    }
}
