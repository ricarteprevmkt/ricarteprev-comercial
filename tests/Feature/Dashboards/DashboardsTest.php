<?php

use App\Actions\Crm\RegisterContract;
use App\Enums\ContractStatus;
use App\Enums\ExpenseStatus;
use App\Enums\OpportunityStage;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Livewire\Executives\Show;
use App\Livewire\Overview;
use App\Metrics\Alerts;
use App\Metrics\ExecutivePerformance;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Catalogs\VisitReason;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Visit;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00'));
    $this->manager = userWithRole(Role::Manager);
    $this->darlan = Executive::factory()->create(['monthly_visits_target' => 60, 'monthly_contracts_target' => 12]);
    $this->darlan->user->update(['name' => 'Darlan da Silva']);
    $this->darlan->user->assignRole('executivo');
    $this->savio = Executive::factory()->create(['monthly_visits_target' => 10, 'monthly_contracts_target' => 2]);
    $this->savio->user->update(['name' => 'Sávio Lima']);
    session([Period::SESSION_KEY => ['preset' => 'mes_atual']]);
});

function doneVisits(Executive $e, int $n, string $date = '2026-11-05 09:00'): void
{
    Visit::factory()->count($n)->create(['executive_id' => $e->id, 'status' => VisitStatus::Done, 'scheduled_at' => $date]);
}

function contractFor(Executive $e, string $date): Contract
{
    $lead = Lead::factory()->create(['executive_id' => $e->id]);
    $o = Opportunity::factory()->create(['lead_id' => $lead->id, 'executive_id' => $e->id]);

    return app(RegisterContract::class)->handle($o, ['status' => ContractStatus::Signed, 'signed_at' => $date, 'contract_value' => '1000'], null);
}

it('mede o ritmo da meta: metade do mês exige metade da meta', function () {
    doneVisits($this->darlan, 20);   // meta 60, esperado 50% em 15/11 → 33% está abaixo
    doneVisits($this->savio, 6);     // meta 10 → 60%
    contractFor($this->savio, '2026-11-10'); // meta 2 → 50%

    $m = new ExecutivePerformance(Period::fromPreset('mes_atual'));
    $g = $m->goalProgress($this->darlan, CarbonImmutable::today());

    expect($g['visits'])->toBe(20)
        ->and($g['visits_pct'])->toBe(33.3)
        ->and($g['expected_pct'])->toBe(50.0)
        ->and($g['below'])->toBeTrue()
        ->and($m->goalProgress($this->savio, CarbonImmutable::today())['below'])->toBeFalse()
        ->and(collect($m->belowGoal())->pluck('executive.id')->all())->toBe([$this->darlan->id]);
});

it('compara realizado com a meta do mês ou a meta padrão', function () {
    Goal::create(['executive_id' => $this->darlan->id, 'month' => '2026-10-01', 'visits_target' => 40, 'contracts_target' => 5]);
    doneVisits($this->darlan, 3, '2026-10-10 09:00');

    $t = (new ExecutivePerformance(Period::fromPreset('mes_atual')))->monthlyVsTargets($this->darlan);

    expect($t['labels'])->toHaveCount(6)
        ->and($t['visits'][4])->toBe(3)
        ->and($t['visits_target'][4])->toBe(40)
        ->and($t['visits_target'][5])->toBe(60);
});

it('agrupa os motivos de visitas não realizadas e monta a série diária', function () {
    $reason = VisitReason::where('slug', 'ausencia-do-contato')->firstOrFail();
    Visit::factory()->count(2)->create(['executive_id' => $this->darlan->id, 'status' => VisitStatus::NotDone, 'not_done_reason_id' => $reason->id, 'scheduled_at' => '2026-11-03 10:00']);
    doneVisits($this->darlan, 2, '2026-11-03 09:00');

    $m = new ExecutivePerformance(Period::fromPreset('mes_atual'));
    $daily = $m->daily($this->darlan);

    expect($m->notDoneReasons($this->darlan))->toBe([['reason' => 'Ausência do contato', 'count' => 2, 'share' => 100.0]])
        ->and($daily['labels'])->toHaveCount(15)
        ->and($daily['visits'][2])->toBe(2);
});

it('mostra a visão geral da equipe ao gestor, com ranking e alertas', function () {
    doneVisits($this->darlan, 5);
    contractFor($this->darlan, '2026-11-08');
    $stale = Opportunity::factory()->create(['executive_id' => $this->savio->id, 'lead_id' => Lead::factory()->create(['executive_id' => $this->savio->id])->id]);
    $stale->forceFill(['last_activity_at' => now()->subDays(30), 'next_action_due' => '2026-11-01'])->save();

    Livewire::actingAs($this->manager)->test(Overview::class)
        ->assertSee('Darlan da Silva')
        ->assertSee('Sávio Lima')
        ->assertSee('Prazos vencidos')
        ->assertSee('Oportunidades sem atualização')
        ->assertSee('Executivos abaixo da meta')
        ->assertViewHas('agenda', fn ($a) => $a['done'] === 5)
        ->assertViewHas('finance', fn ($f) => $f['contracts_signed'] === 1);
});

it('mostra ao executivo só os próprios números e a própria meta', function () {
    doneVisits($this->darlan, 4);
    doneVisits($this->savio, 9);

    Livewire::actingAs($this->darlan->user)->test(Overview::class)
        ->assertViewHas('agenda', fn ($a) => $a['done'] === 4)
        ->assertViewHas('teamView', false)
        ->assertSee('Minha meta')
        ->assertSee('Meu desempenho')
        ->assertDontSee('Sávio Lima');
});

it('alertas respeitam o perfil e o recorte do executivo', function () {
    $mine = Opportunity::factory()->create(['executive_id' => $this->darlan->id, 'lead_id' => Lead::factory()->create(['executive_id' => $this->darlan->id])->id]);
    $mine->forceFill(['next_action_due' => '2026-11-01'])->save();
    $other = Opportunity::factory()->create(['executive_id' => $this->savio->id, 'lead_id' => Lead::factory()->create(['executive_id' => $this->savio->id])->id]);
    $other->forceFill(['next_action_due' => '2026-11-01'])->save();
    $expense = new Expense(['description' => 'x', 'expense_category_id' => ExpenseCategory::first()->id, 'amount' => '10', 'spent_on' => '2026-11-10', 'executive_id' => $this->darlan->id]);
    $expense->save();

    $mineAlerts = collect(Alerts::for($this->darlan->user))->keyBy('key');
    $teamAlerts = collect(Alerts::for($this->manager))->keyBy('key');

    expect($mineAlerts['overdue']['count'])->toBe(1)
        ->and($mineAlerts->has('pending_expenses'))->toBeFalse()
        ->and($mineAlerts->has('below_goal'))->toBeFalse()
        ->and($teamAlerts['overdue']['count'])->toBe(2)
        ->and($teamAlerts['pending_expenses']['count'])->toBe(1);

    expect(Expense::first()->status)->toBe(ExpenseStatus::Pending);
});

it('abre o desempenho individual para o próprio executivo e para quem vê a equipe', function () {
    doneVisits($this->darlan, 3);

    Livewire::actingAs($this->darlan->user)->test(Show::class, ['executive' => $this->darlan])
        ->assertOk()
        ->assertViewHas('s', fn ($s) => $s['visits_done'] === 3)
        ->assertDontSee('Comparativo de desempenho');

    Livewire::actingAs($this->darlan->user)->test(Show::class, ['executive' => $this->savio])->assertForbidden();

    Livewire::actingAs($this->manager)->test(Show::class, ['executive' => $this->darlan])
        ->assertSee('Comparativo de desempenho')
        ->set('compareBy', 'contracts')
        ->assertOk();
});

it('nega a visão geral ao indicador', function () {
    $this->actingAs(userWithRole(Role::Indicator))->get(route('dashboard'))->assertForbidden();
    expect(OpportunityStage::Contact->rank())->toBe(1);
});
