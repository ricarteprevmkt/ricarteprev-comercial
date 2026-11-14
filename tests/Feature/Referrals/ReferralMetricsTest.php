<?php

use App\Actions\Referrals\RegisterReferral;
use App\Enums\Role;
use App\Livewire\Referrals\Dashboard;
use App\Metrics\ReferralMetrics;
use App\Models\City;
use App\Models\Referral;
use App\Models\Region;
use App\Models\RegionalIndicator;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $this->period = Period::custom('2026-11-01', '2026-11-30');
    $this->cariri = Region::create(['name' => 'Cariri']);
    $this->juazeiro = City::forceCreate(['ibge_code' => 2307304, 'name' => 'Juazeiro do Norte', 'uf' => 'CE', 'latitude' => -7.19, 'longitude' => -39.3]);
    $this->crato = City::forceCreate(['ibge_code' => 2304202, 'name' => 'Crato', 'uf' => 'CE', 'latitude' => -7.21, 'longitude' => -39.41]);
    $this->joao = RegionalIndicator::factory()->create(['name' => 'João Alves', 'region_id' => $this->cariri->id]);
    $this->maria = RegionalIndicator::factory()->create(['name' => 'Maria Souza']);
});

function seedReferral(RegionalIndicator $indicator, City $city, int $rank, string $date = '2026-11-10'): Referral
{
    static $n = 0;
    $n++;
    $r = app(RegisterReferral::class)->handle($indicator, [
        'name' => "Pessoa {$n}", 'phone' => '8899'.str_pad((string) $n, 7, '0', STR_PAD_LEFT), 'city_id' => $city->id,
        'referred_at' => $date, 'consent' => true,
    ], null);
    $r->forceFill(['furthest_rank' => $rank])->save();

    return $r;
}

it('conta indicações válidas, etapas alcançadas e conversão, sem duplicadas', function () {
    seedReferral($this->joao, $this->juazeiro, 1);
    seedReferral($this->joao, $this->juazeiro, 3);
    seedReferral($this->joao, $this->crato, 6);
    seedReferral($this->maria, $this->crato, 4);
    seedReferral($this->maria, $this->crato, 1, '2026-10-15'); // fora do período
    $dup = seedReferral($this->maria, $this->crato, 1);
    $dup->forceFill(['is_duplicate' => true])->save();

    $t = (new ReferralMetrics($this->period))->totals();

    expect($t['total'])->toBe(4)
        ->and($t['qualified'])->toBe(3)
        ->and($t['opportunities'])->toBe(2)
        ->and($t['signed'])->toBe(1)
        ->and($t['duplicates'])->toBe(1)
        ->and($t['conversion'])->toBe(25.0);
});

it('ranqueia por indicador e por cidade e filtra por região', function () {
    seedReferral($this->joao, $this->juazeiro, 1);
    seedReferral($this->joao, $this->juazeiro, 6);
    seedReferral($this->maria, $this->crato, 1);

    $m = new ReferralMetrics($this->period);
    expect($m->byIndicator()[0]['indicator']->name)->toBe('João Alves')
        ->and($m->byIndicator()[0]['total'])->toBe(2)
        ->and($m->byCity()[0]['city']->name)->toBe('Juazeiro do Norte')
        ->and($m->mapPoints())->toHaveCount(2);

    $cariri = new ReferralMetrics($this->period, regionId: $this->cariri->id);
    expect($cariri->totals()['total'])->toBe(2)
        ->and(collect($cariri->byIndicator())->pluck('indicator.name')->all())->toBe(['João Alves']);
});

it('mostra o painel só para quem tem visão geral', function () {
    seedReferral($this->joao, $this->juazeiro, 1);
    session([Period::SESSION_KEY => ['preset' => 'personalizado', 'start' => '2026-11-01', 'end' => '2026-11-30']]);

    Livewire::actingAs(userWithRole(Role::Manager))->test(Dashboard::class)
        ->assertSee('João Alves')
        ->assertViewHas('totals', fn ($t) => $t['total'] === 1);

    Livewire::actingAs(userWithRole(Role::Executive))->test(Dashboard::class)->assertForbidden();
});
