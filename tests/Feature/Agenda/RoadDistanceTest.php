<?php

use App\Actions\Agenda\BuildRoute;
use App\Enums\VisitType;
use App\Metrics\AgendaMetrics;
use App\Models\City;
use App\Models\Executive;
use App\Models\Route;
use App\Models\Visit;
use App\Support\RoadDistance;
use Carbon\CarbonImmutable;
use Database\Seeders\CitySeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed([CitySeeder::class, SystemSettingsSeeder::class]);
    config(['services.openrouteservice.key' => 'chave-de-teste']);

    $this->juazeiro = City::where('ibge_code', 2307304)->firstOrFail();
    $this->exec = Executive::factory()->create(['city_id' => $this->juazeiro->id]);
    $this->date = CarbonImmutable::parse('2026-11-16');

    foreach ([['-7.2100000', '-39.3100000', '08:00'], ['-7.2340000', '-39.4100000', '10:00']] as [$lat, $lng, $time]) {
        Visit::factory()->create([
            'executive_id' => $this->exec->id, 'type' => VisitType::FieldVisit,
            'scheduled_at' => "2026-11-16 {$time}", 'latitude' => $lat, 'longitude' => $lng,
        ]);
    }
});

function orsResponse(array $metersPerLeg): array
{
    return ['routes' => [['segments' => array_map(fn ($m) => ['distance' => $m], $metersPerLeg)]]];
}

it('usa a distância pelas estradas e guarda cada trecho em cache', function () {
    Http::fake(['api.openrouteservice.org/*' => Http::response(orsResponse([4210, 12850]))]);

    $route = app(BuildRoute::class)->handle($this->exec, $this->date);

    expect($route->km_source)->toBe('road')
        ->and((float) $route->km_estimated)->toBe(17.1)
        ->and((float) $route->stops()->first()->leg_km)->toBe(4.2);

    Http::assertSent(function (Request $r) {
        $body = $r->data();

        return $r->hasHeader('Authorization', 'chave-de-teste')
            && count($body['coordinates']) === 3
            && $body['coordinates'][1] === [-39.31, -7.21] // [longitude, latitude]
            && ! array_key_exists('name', $body);
    });

    // Mesma rota de novo: vem do cache, sem nova consulta.
    app(BuildRoute::class)->handle($this->exec, $this->date);
    Http::assertSentCount(1);

    expect(AgendaMetrics::day($this->date)->summary()['by_road'])->toBeTrue();
});

it('usa o fator de estrada quando o serviço falha e pausa novas consultas', function () {
    Http::fake(['api.openrouteservice.org/*' => Http::response(['error' => 'Quota exceeded'], 429)]);

    $route = app(BuildRoute::class)->handle($this->exec, $this->date);

    expect($route->km_source)->toBe('estimate')
        ->and((float) $route->km_estimated)->toBeGreaterThan(0)
        ->and(RoadDistance::paused())->toBeTrue()
        ->and(AgendaMetrics::day($this->date)->summary()['by_road'])->toBeFalse();

    app(BuildRoute::class)->handle($this->exec, $this->date);
    Http::assertSentCount(1);
});

it('sem chave configurada não consulta o serviço', function () {
    config(['services.openrouteservice.key' => null]);
    Http::fake();

    $route = app(BuildRoute::class)->handle($this->exec, $this->date);

    expect($route->km_source)->toBe('estimate');
    Http::assertNothingSent();
});

it('recalcula as rotas antigas pelo comando', function () {
    config(['services.openrouteservice.key' => null]);
    app(BuildRoute::class)->handle($this->exec, $this->date);

    config(['services.openrouteservice.key' => 'chave-de-teste']);
    Http::fake(['api.openrouteservice.org/*' => Http::response(orsResponse([4210, 12850]))]);

    $this->travelTo($this->date->setTime(18, 0));
    $this->artisan('app:rebuild-routes', ['--only-estimated' => true])
        ->expectsOutputToContain('1 pelas estradas')
        ->assertSuccessful();

    expect(Route::first()->km_source)->toBe('road');
});
