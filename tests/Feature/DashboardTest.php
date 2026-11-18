<?php

use App\Actions\Crm\RegisterContract;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Metrics\DashboardMetrics;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Support\Navigation;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00'));
});

function dashContract(Executive $exec, string $signedAt, string $value): void
{
    $lead = Lead::factory()->create(['executive_id' => $exec->id]);
    $o = Opportunity::factory()->create(['lead_id' => $lead->id, 'executive_id' => $exec->id]);
    app(RegisterContract::class)->handle($o, ['status' => ContractStatus::Signed, 'signed_at' => $signedAt, 'contract_value' => $value], null);
}

it('projeta o mês pelo ritmo e os próximos meses pela tendência', function () {
    // Dia 15 de um mês de 30 dias: metade do mês, o dobro do realizado.
    expect(DashboardMetrics::paceProjection(10, CarbonImmutable::parse('2026-11-15')))->toBe(20.0)
        ->and(DashboardMetrics::linearForecast([2, 4, 6, 8], 2))->toBe([10.0, 12.0])
        // Tendência de queda nunca projeta valor negativo.
        ->and(DashboardMetrics::linearForecast([9, 6, 3, 0], 2))->toBe([0.0, 0.0])
        ->and(DashboardMetrics::linearForecast([5, 7], 1))->toBe([6.0]);
});

it('calcula os indicadores, a série mensal e a projeção', function () {
    $exec = Executive::factory()->create(['monthly_contracts_target' => 4]);
    dashContract($exec, '2026-09-10', '1000.00');
    dashContract($exec, '2026-10-10', '2000.00');
    dashContract($exec, '2026-11-05', '3000.00');
    dashContract($exec, '2026-11-12', '1000.00');

    $m = new DashboardMetrics(Period::custom('2026-11-01', '2026-11-15'), null, 6);
    $kpis = collect($m->kpis())->keyBy('key');

    expect($kpis['contracts']['value'])->toBe(2.0)
        ->and($kpis['contracted']['value'])->toBe(4000.0)
        ->and($kpis['ticket']['value'])->toBe(2000.0)
        ->and($m->monthly()['contracts'])->toBe([0.0, 0.0, 0.0, 1.0, 1.0, 2.0]);

    $trend = $m->trend('contracts');
    expect($trend['labels'])->toHaveCount(9)
        ->and($trend['projected_month'])->toBe(4.0)
        ->and($trend['actual'][5])->toBe(2.0)
        ->and($trend['actual'][6])->toBeNull()
        ->and($trend['projection'][4])->toBe(1.0);

    $goal = collect($m->monthGoal()['items'])->firstWhere('label', 'Contratos');
    expect($goal)->toMatchArray(['done' => 2, 'target' => 4, 'pct' => 50.0, 'projected_pct' => 100.0]);
});

it('mostra o dashboard com filtro por executivo e só os próprios números ao executivo', function () {
    $admin = userWithRole(Role::Admin);
    $a = Executive::factory()->create();
    $b = Executive::factory()->create();
    dashContract($a, '2026-11-05', '5000.00');
    dashContract($b, '2026-11-06', '7000.00');
    $this->actingAs($admin)->withSession(['period' => ['preset' => 'mes_atual']]);

    $this->get(route('analytics'))->assertOk();
    Livewire::actingAs($admin)->test(Dashboard::class)
        ->assertSee('Receita contratada')->assertSee('R$ 12.000')->assertSee('Evolução e projeção')
        ->set('executiveFilter', (string) $a->id)
        ->assertSee('R$ 5.000')->assertDontSee('R$ 12.000');

    $user = userWithRole(Role::Executive);
    $own = Executive::factory()->create(['user_id' => $user->id]);
    dashContract($own, '2026-11-07', '900.00');
    $this->actingAs($user)->withSession(['period' => ['preset' => 'mes_atual']]);
    Livewire::actingAs($user)->test(Dashboard::class)->assertSee('R$ 900')->assertDontSee('R$ 12.000');

    expect(collect(Navigation::for($admin))->flatMap(fn ($g) => array_column($g['items'], 'label')))->toContain('Dashboard');
    $this->actingAs(userWithRole(Role::Indicator))->get(route('analytics'))->assertForbidden();
});
