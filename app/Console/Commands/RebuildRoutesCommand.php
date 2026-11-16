<?php

namespace App\Console\Commands;

use App\Actions\Agenda\BuildRoute;
use App\Models\Executive;
use App\Models\Route;
use App\Support\RoadDistance;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recalcula as rotas de um intervalo. Usado depois de configurar a chave do
 * OpenRouteService, para trocar as distâncias estimadas pelas das estradas.
 * Respeita o limite gratuito de 40 consultas por minuto.
 */
#[Signature('app:rebuild-routes {--from= : Data inicial (AAAA-MM-DD; padrão: 30 dias atrás)} {--to= : Data final (padrão: hoje)} {--only-estimated : Só as rotas ainda calculadas pelo fator de estrada}')]
#[Description('Recalcula as distâncias das rotas (pelas estradas, quando configurado)')]
class RebuildRoutesCommand extends Command
{
    public function handle(BuildRoute $builder, RoadDistance $distance): int
    {
        if (! RoadDistance::enabled()) {
            $this->warn('ORS_API_KEY não está configurada: as rotas serão recalculadas pelo fator de estrada.');
        }

        $from = CarbonImmutable::parse($this->option('from') ?: now()->subDays(30)->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($this->option('to') ?: now()->toDateString())->startOfDay();

        $routes = Route::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($this->option('only-estimated'), fn ($q) => $q->where('km_source', 'estimate'))
            ->orderBy('date')->get(['id', 'executive_id', 'date']);

        $bar = $this->output->createProgressBar($routes->count());
        $counts = [RoadDistance::ROAD => 0, RoadDistance::ESTIMATE => 0];

        foreach ($routes as $route) {
            $before = $distance->requests;
            $executive = Executive::withTrashed()->find($route->executive_id);
            $rebuilt = $executive ? $builder->handle($executive, $route->date) : null;

            if ($rebuilt?->km_source !== null) {
                $counts[$rebuilt->km_source]++;
            }
            $bar->advance();

            if ($distance->requests > $before) {
                usleep(1_600_000); // 40 consultas por minuto no plano gratuito
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$routes->count()} rota(s) recalculada(s): {$counts[RoadDistance::ROAD]} pelas estradas, {$counts[RoadDistance::ESTIMATE]} pelo fator de estrada.");

        if ($counts[RoadDistance::ESTIMATE] > 0 && RoadDistance::enabled()) {
            $this->warn('Parte das rotas ficou pelo fator (serviço indisponível ou limite diário). Rode de novo mais tarde com --only-estimated.');
        }

        return self::SUCCESS;
    }
}
