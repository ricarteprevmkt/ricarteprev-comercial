<?php

namespace App\Reports\Definitions;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 5. Oportunidades abertas no período. */
final class OpportunitiesReport extends Report
{
    public function key(): string
    {
        return 'oportunidades';
    }

    public function title(): string
    {
        return 'Oportunidades';
    }

    public function description(): string
    {
        return 'Oportunidades abertas no período, com etapa atual, próxima ação e prazo.';
    }

    public function filters(): array
    {
        return ['executive', 'region', 'city', 'source', 'indicator', 'stage'];
    }

    public function columns(): array
    {
        return [
            'opened' => ['Aberta em', 'text'],
            'client' => ['Cliente', 'text'],
            'executive' => ['Executivo', 'text'],
            'city' => ['Cidade', 'text'],
            'source' => ['Origem', 'text'],
            'indicator' => ['Indicado por', 'text'],
            'stage' => ['Etapa', 'text'],
            'value' => ['Valor estimado', 'money'],
            'next_action' => ['Próxima ação', 'text'],
            'due' => ['Prazo', 'text'],
            'last' => ['Última atividade', 'text'],
            'loss' => ['Motivo de encerramento', 'text'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $ids = $filters->executiveIds();

        return Opportunity::query()
            ->with(['lead.city', 'executive.user', 'source', 'indicator', 'lossReason'])
            ->whereBetween('opportunities.created_at', $filters->period->range())
            ->when($ids !== null, fn ($q) => $q->whereIn('executive_id', $ids ?: [0]))
            ->when($filters->sourceId, fn ($q) => $q->where('lead_source_id', $filters->sourceId))
            ->when($filters->indicatorId, fn ($q) => $q->where('regional_indicator_id', $filters->indicatorId))
            ->when($filters->stage && OpportunityStage::tryFrom($filters->stage), fn ($q) => $q->where('stage', $filters->stage))
            ->when($filters->cityId, fn ($q) => $q->whereHas('lead', fn ($q) => $q->where('city_id', $filters->cityId)))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Opportunity $o) => [
                'opened' => $o->created_at?->format('d/m/Y'),
                'client' => $o->lead->name,
                'executive' => $o->executive->user->name,
                'city' => $o->lead->city?->name,
                'source' => $o->source?->name,
                'indicator' => $o->indicator?->name,
                'stage' => $o->stage->label(),
                'value' => $o->estimated_value,
                'next_action' => $o->next_action,
                'due' => $o->next_action_due?->format('d/m/Y'),
                'last' => $o->last_activity_at?->format('d/m/Y'),
                'loss' => $o->lossReason?->name,
            ])->values()->all();
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return $rows === [] ? null : $this->sum($rows, ['value'], 'opened', count($rows).' oportunidade(s)');
    }
}
