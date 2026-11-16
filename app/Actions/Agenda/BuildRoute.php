<?php

namespace App\Actions\Agenda;

use App\Enums\VisitStatus;
use App\Models\City;
use App\Models\Executive;
use App\Models\Route;
use App\Models\Visit;
use App\Support\RoadDistance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula a rota de um executivo num dia.
 *
 * - Paradas: atividades presenciais planejadas do dia (exceto canceladas e
 *   reagendadas) que tenham coordenadas, na ordem do horário.
 * - Ponto de partida: centro da cidade base do executivo, quando cadastrada.
 * - Não inclui a volta para a base.
 * - Distância pelas estradas (OpenRouteService); sem o serviço, linha reta ×
 *   fator de estrada. A rota guarda qual dos dois foi usado (km_source).
 * - Pontos aproximados (centro da cidade, sem endereço marcado no mapa)
 *   deixam a rota marcada como aproximada.
 */
final class BuildRoute
{
    public function __construct(private RoadDistance $distance) {}

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

        // A consulta ao serviço de rotas acontece antes da transação, para não segurar o banco.
        $base = $executive->city_id ? City::query()->find($executive->city_id) : null;
        $start = $base && $base->latitude !== null ? [(float) $base->latitude, (float) $base->longitude] : null;
        $points = array_values($visits->map(fn (Visit $v) => [(float) $v->latitude, (float) $v->longitude])->all());
        $result = $this->distance->legs($start ? [$start, ...$points] : $points);
        // Trecho que chega a cada parada (a primeira parada sem ponto de partida não tem trecho).
        $legs = $start ? $result['legs'] : [null, ...$result['legs']];

        return DB::transaction(function () use ($executive, $date, $visits, $legs, $result) {
            $route = Route::query()->where('executive_id', $executive->id)->whereDate('date', $date->toDateString())->first();

            if ($visits->isEmpty()) {
                $route?->delete();

                return null;
            }

            $route ??= new Route(['executive_id' => $executive->id, 'date' => $date->toDateString()]);
            $route->save();
            $route->stops()->delete();

            $total = 0.0;
            $approximate = false;

            foreach ($visits as $i => $visit) {
                $leg = $legs[$i] ?? null;
                $total += $leg ?? 0;
                $approximate = $approximate || $visit->location_source === 'city';

                $route->stops()->create(['visit_id' => $visit->id, 'sequence' => $i + 1, 'leg_km' => $leg]);
            }

            $route->fill([
                'km_estimated' => round($total, 1),
                'stops_count' => $visits->count(),
                'has_approximate_points' => $approximate,
                'km_source' => $result['source'],
            ])->save();

            return $route;
        });
    }
}
