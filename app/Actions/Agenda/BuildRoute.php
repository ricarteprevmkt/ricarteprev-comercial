<?php

namespace App\Actions\Agenda;

use App\Enums\VisitStatus;
use App\Models\City;
use App\Models\Executive;
use App\Models\Route;
use App\Models\Visit;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula a rota estimada de um executivo num dia.
 *
 * - Paradas: atividades presenciais planejadas do dia (exceto canceladas e
 *   reagendadas) que tenham coordenadas, na ordem do horário.
 * - Ponto de partida: centro da cidade base do executivo, quando cadastrada.
 * - Não inclui a volta para a base.
 * - Pontos aproximados (centro da cidade, sem endereço marcado no mapa)
 *   deixam a rota marcada como aproximada.
 */
final class BuildRoute
{
    public function handle(Executive $executive, CarbonImmutable $date): ?Route
    {
        $date = $date->startOfDay();

        $visits = Visit::query()
            ->where('executive_id', $executive->id)
            ->onDate($date)
            ->whereNotIn('status', [VisitStatus::Cancelled->value, VisitStatus::Rescheduled->value])
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('scheduled_at')->orderBy('id')
            ->get()
            ->filter(fn (Visit $v) => $v->type->isOnSite())
            ->values();

        return DB::transaction(function () use ($executive, $date, $visits) {
            $route = Route::query()->where('executive_id', $executive->id)->whereDate('date', $date->toDateString())->first();

            if ($visits->isEmpty()) {
                $route?->delete();

                return null;
            }

            $route ??= new Route(['executive_id' => $executive->id, 'date' => $date->toDateString()]);
            $route->save();
            $route->stops()->delete();

            $base = $executive->city_id ? City::query()->find($executive->city_id) : null;
            $prev = $base && $base->latitude !== null ? [(float) $base->latitude, (float) $base->longitude] : null;
            $factor = Geo::roadFactor();
            $total = 0.0;
            $approximate = false;

            foreach ($visits as $i => $visit) {
                $point = [(float) $visit->latitude, (float) $visit->longitude];
                $leg = $prev ? Geo::roadKm($prev[0], $prev[1], $point[0], $point[1], $factor) : null;
                $total += $leg ?? 0;
                $approximate = $approximate || $visit->location_source === 'city';

                $route->stops()->create(['visit_id' => $visit->id, 'sequence' => $i + 1, 'leg_km' => $leg]);
                $prev = $point;
            }

            $route->fill([
                'km_estimated' => round($total, 1),
                'stops_count' => $visits->count(),
                'has_approximate_points' => $approximate,
            ])->save();

            return $route;
        });
    }
}
