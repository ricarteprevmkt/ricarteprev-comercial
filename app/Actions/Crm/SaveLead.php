<?php

namespace App\Actions\Crm;

use App\Enums\LeadType;
use App\Enums\OpportunityStage;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityHistory;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou atualiza um contato e, opcionalmente, abre uma oportunidade para ele.
 *
 * Duplicidade: mesmo CPF/CNPJ é sempre bloqueado; mesmo telefone é bloqueado
 * a menos que o usuário confirme que é outra pessoa ($allowSamePhone).
 */
final class SaveLead
{
    public function __construct(private FindDuplicateLead $duplicates) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array{stage: OpportunityStage, interest?: string|null, estimated_value?: string|null, next_action?: string|null, next_action_due?: string|null}|null  $opportunity
     * @return array{lead: Lead, opportunity: Opportunity|null}
     */
    public function handle(?Lead $lead, array $data, ?string $document, ?array $opportunity, ?User $user, bool $allowSamePhone = false): array
    {
        $data['phone'] = Phone::normalize($data['phone'] ?? null);
        $data['whatsapp'] = Phone::normalize($data['whatsapp'] ?? null);

        $duplicate = $this->duplicates->handle($document, $data['phone'], $data['whatsapp'], $lead?->id);

        if ($duplicate && ($duplicate['by'] === 'document' || ! $allowSamePhone)) {
            $type = LeadType::from((string) ($data['type'] ?? 'pf'));
            throw ValidationException::withMessages([
                $duplicate['by'] === 'document' ? 'form.document' : 'form.phone' => $duplicate['by'] === 'document'
                    ? "Já existe um contato com este {$type->documentLabel()}."
                    : 'Já existe um contato com este telefone. Se for outra pessoa, confirme abaixo.',
            ])->errorBag('default');
        }

        return DB::transaction(function () use ($lead, $data, $document, $opportunity, $user) {
            $lead ??= new Lead(['created_by' => $user?->id]);
            $lead->fill($data);
            $lead->setDocument($document);
            $lead->save();

            $created = null;

            if ($opportunity !== null) {
                $created = $this->openOpportunity($lead, $opportunity, $user);
            }

            return ['lead' => $lead, 'opportunity' => $created];
        });
    }

    /**
     * @param  array{stage: OpportunityStage, interest?: string|null, estimated_value?: string|null, next_action?: string|null, next_action_due?: string|null}  $data
     */
    public function openOpportunity(Lead $lead, array $data, ?User $user): Opportunity
    {
        $stage = $data['stage'];

        if ($stage->rank() === null || $stage === OpportunityStage::Signed) {
            throw ValidationException::withMessages(['opportunity.stage' => 'Escolha uma etapa inicial do funil antes do contrato.']);
        }

        if ($lead->executive_id === null) {
            throw ValidationException::withMessages(['form.executive_id' => 'Defina o executivo responsável para abrir a oportunidade.']);
        }

        $now = now();
        $opportunity = new Opportunity([
            'lead_id' => $lead->id,
            'executive_id' => $lead->executive_id,
            'lead_source_id' => $lead->lead_source_id,
            'regional_indicator_id' => $lead->regional_indicator_id,
            'interest' => $data['interest'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
            'next_action' => $data['next_action'] ?? null,
            'next_action_due' => $data['next_action_due'] ?? null,
            'created_by' => $user?->id,
        ]);
        $opportunity->stage = $stage;
        $opportunity->furthest_stage_rank = (int) $stage->rank();
        $opportunity->stage_changed_at = $now;
        $opportunity->last_activity_at = $now;
        $opportunity->save();

        OpportunityHistory::query()->create([
            'opportunity_id' => $opportunity->id,
            'from_stage' => null,
            'to_stage' => $stage,
            'user_id' => $user?->id,
            'note' => 'Oportunidade aberta',
            'created_at' => $now,
        ]);

        return $opportunity;
    }
}
