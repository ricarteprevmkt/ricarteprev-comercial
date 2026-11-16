<?php

namespace App\Reports;

use App\Enums\OpportunityStage;
use App\Models\Catalogs\LeadSource;
use App\Models\City;
use App\Models\Executive;
use App\Models\Region;
use App\Models\RegionalIndicator;

/**
 * Descrição legível dos filtros aplicados (cabeçalho do PDF e da tela).
 */
final class ReportFilterLabels
{
    /** @return array<string, string> */
    public static function describe(ReportFilters $f): array
    {
        $labels = ['Período' => $f->period->start->format('d/m/Y').' a '.$f->period->end->format('d/m/Y')];

        if ($f->executiveId) {
            $labels['Executivo'] = (string) Executive::withTrashed()->with('user')->find($f->executiveId)?->user->name;
        }
        if ($f->regionId) {
            $labels['Região'] = (string) Region::withTrashed()->whereKey($f->regionId)->value('name');
        }
        if ($f->cityId) {
            $labels['Cidade'] = (string) City::query()->find($f->cityId)?->label();
        }
        if ($f->indicatorId) {
            $labels['Parceiro'] = (string) RegionalIndicator::withTrashed()->whereKey($f->indicatorId)->value('name');
        }
        if ($f->sourceId) {
            $labels['Origem'] = (string) LeadSource::query()->whereKey($f->sourceId)->value('name');
        }
        if ($f->stage && ($stage = OpportunityStage::tryFrom($f->stage))) {
            $labels['Etapa'] = $stage->label();
        }
        if ($f->allowedExecutives !== null) {
            $labels['Recorte'] = 'Somente os próprios registros';
        }

        return $labels;
    }
}
