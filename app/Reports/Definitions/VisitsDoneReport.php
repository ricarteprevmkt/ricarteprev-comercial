<?php

namespace App\Reports\Definitions;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 3. Visitas realizadas. */
final class VisitsDoneReport extends Report
{
    public function key(): string
    {
        return 'visitas-realizadas';
    }

    public function title(): string
    {
        return 'Visitas realizadas';
    }

    public function description(): string
    {
        return 'Visitas concluídas no período, com resultado, próxima ação e quilometragem.';
    }

    public function filters(): array
    {
        return ['executive', 'region', 'city'];
    }

    public function columns(): array
    {
        return [
            'date' => ['Data', 'text'],
            'executive' => ['Executivo', 'text'],
            'contact' => ['Contato', 'text'],
            'city' => ['Cidade', 'text'],
            'neighborhood' => ['Bairro', 'text'],
            'type' => ['Tipo', 'text'],
            'outcome' => ['Resultado', 'text'],
            'next_action' => ['Próxima ação', 'text'],
            'km' => ['Km', 'decimal'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $ids = $filters->executiveIds();

        return Visit::query()
            ->with(['executive.user', 'lead', 'city'])
            ->where('status', VisitStatus::Done->value)
            ->whereBetween('scheduled_at', $filters->period->range())
            ->when($ids !== null, fn ($q) => $q->whereIn('executive_id', $ids ?: [0]))
            ->when($filters->cityId, fn ($q) => $q->where('city_id', $filters->cityId))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Visit $v) => [
                'date' => ($v->started_at ?? $v->scheduled_at)->format('d/m/Y H:i'),
                'executive' => $v->executive->user->name,
                'contact' => $v->displayName(),
                'city' => $v->city?->name,
                'neighborhood' => $v->neighborhood,
                'type' => $v->type->label(),
                'outcome' => $v->outcome,
                'next_action' => trim(($v->next_action ?? '').($v->next_contact_at ? ' ('.$v->next_contact_at->format('d/m/Y').')' : '')),
                'km' => $v->km_reported,
            ])->values()->all();
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return $rows === [] ? null : $this->sum($rows, ['km'], 'date', count($rows).' visita(s)');
    }
}
