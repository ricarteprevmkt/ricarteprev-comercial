<?php

namespace App\Reports\Definitions;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 4. Visitas não realizadas. */
final class VisitsNotDoneReport extends Report
{
    public function key(): string
    {
        return 'visitas-nao-realizadas';
    }

    public function title(): string
    {
        return 'Visitas não realizadas';
    }

    public function description(): string
    {
        return 'Visitas que não aconteceram, com o motivo informado.';
    }

    public function filters(): array
    {
        return ['executive', 'region', 'city'];
    }

    public function columns(): array
    {
        return [
            'date' => ['Data agendada', 'text'],
            'executive' => ['Executivo', 'text'],
            'contact' => ['Contato', 'text'],
            'city' => ['Cidade', 'text'],
            'type' => ['Tipo', 'text'],
            'reason' => ['Motivo', 'text'],
            'note' => ['Observação', 'text'],
            'rescheduled' => ['Reagendada', 'text'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $ids = $filters->executiveIds();

        return Visit::query()
            ->with(['executive.user', 'lead', 'city', 'notDoneReason'])
            ->where('status', VisitStatus::NotDone->value)
            ->whereBetween('scheduled_at', $filters->period->range())
            ->when($ids !== null, fn ($q) => $q->whereIn('executive_id', $ids ?: [0]))
            ->when($filters->cityId, fn ($q) => $q->where('city_id', $filters->cityId))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Visit $v) => [
                'date' => $v->scheduled_at->format('d/m/Y H:i'),
                'executive' => $v->executive->user->name,
                'contact' => $v->displayName(),
                'city' => $v->city?->name,
                'type' => $v->type->label(),
                'reason' => $v->notDoneReason->name ?? 'Sem motivo',
                'note' => $v->outcome,
                'rescheduled' => $v->rescheduled_to_id ? 'Sim' : 'Não',
            ])->values()->all();
    }
}
