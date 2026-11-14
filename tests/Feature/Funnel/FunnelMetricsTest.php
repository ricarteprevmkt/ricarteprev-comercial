<?php

use App\Actions\Crm\RegisterContract;
use App\Enums\ContractStatus;
use App\Enums\OpportunityStage;
use App\Enums\Role;
use App\Livewire\Funnel\Dashboard;
use App\Metrics\FunnelMetrics;
use App\Models\Catalogs\LeadSource;
use App\Models\Catalogs\LossReason;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00'));
    $this->period = Period::custom('2026-11-01', '2026-11-30');
    $this->exec = Executive::factory()->create();
    $this->other = Executive::factory()->create();
});

function opp(Executive $executive, OpportunityStage $stage, ?int $furthest = null, string $createdAt = '2026-11-10', ?int $sourceId = null): Opportunity
{
    $lead = Lead::factory()->create(['executive_id' => $executive->id, 'lead_source_id' => $sourceId]);
    $o = Opportunity::factory()->stage($stage, $furthest)->create([
        'lead_id' => $lead->id, 'executive_id' => $executive->id, 'lead_source_id' => $sourceId,
    ]);
    $o->forceFill(['created_at' => $createdAt.' 09:00:00'])->save();

    return $o;
}

it('conta a coorte do período e quem chegou a cada etapa, incluindo etapas puladas', function () {
    opp($this->exec, OpportunityStage::Contact);
    opp($this->exec, OpportunityStage::Qualified);
    opp($this->exec, OpportunityStage::Proposal);           // pulou "agendado": conta nele também
    opp($this->exec, OpportunityStage::Lost, furthest: 2);  // perdida depois de qualificada
    opp($this->exec, OpportunityStage::FollowUp, furthest: 2); // fora da base
    opp($this->exec, OpportunityStage::Contact, createdAt: '2026-10-20'); // fora do período

    $f = (new FunnelMetrics($this->period))->funnel();

    expect($f['base'])->toBe(4)
        ->and(array_column($f['stages'], 'count'))->toBe([4, 3, 1, 1, 0])
        ->and($f['stages'][1]['conversion'])->toBe(75.0)
        ->and($f['stages'][2]['conversion'])->toBe(33.3)
        ->and($f['overall'])->toBe(0.0);
});

it('conta contratos assinados pela data de assinatura e calcula o tempo médio', function () {
    $o = opp($this->exec, OpportunityStage::Proposal, createdAt: '2026-11-01');
    app(RegisterContract::class)->handle($o, [
        'status' => ContractStatus::Signed, 'signed_at' => '2026-11-11', 'contract_value' => '3500.00',
    ], null);

    $pending = opp($this->exec, OpportunityStage::Proposal);
    app(RegisterContract::class)->handle($pending, ['status' => ContractStatus::Pending, 'contract_value' => '999'], null);

    $m = new FunnelMetrics($this->period);

    expect($m->contractsSigned())->toBe(1)
        ->and($m->contractedRevenue())->toBe('3500.00')
        ->and($m->averageDaysToSign())->toBe(10.0)
        ->and($m->funnel()['overall'])->toBe(50.0);
});

it('recorta por executivo', function () {
    opp($this->exec, OpportunityStage::Qualified);
    opp($this->other, OpportunityStage::Contact);
    opp($this->other, OpportunityStage::Contact);

    expect((new FunnelMetrics($this->period, [$this->exec->id]))->funnel()['base'])->toBe(1)
        ->and((new FunnelMetrics($this->period, [$this->other->id]))->funnel()['base'])->toBe(2)
        ->and((new FunnelMetrics($this->period, []))->funnel()['base'])->toBe(0);

    $rows = (new FunnelMetrics($this->period))->byExecutive();
    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->firstWhere('executive.id', $this->exec->id)['qualified'])->toBe(1);
});

it('agrupa por origem e por motivo de encerramento', function () {
    $instagram = LeadSource::where('slug', 'instagram')->firstOrFail();
    $reason = LossReason::where('slug', 'contato-nao-localizado')->firstOrFail();

    opp($this->exec, OpportunityStage::Contact, sourceId: $instagram->id);
    opp($this->exec, OpportunityStage::Contact, sourceId: $instagram->id);
    opp($this->exec, OpportunityStage::Contact);
    $lost = opp($this->exec, OpportunityStage::Lost, furthest: 1);
    $lost->forceFill(['loss_reason_id' => $reason->id, 'closed_at' => '2026-11-15 10:00:00'])->save();

    $m = new FunnelMetrics($this->period);
    $sources = collect($m->bySource())->keyBy('source');

    expect($sources['Instagram']['base'])->toBe(2)
        ->and($sources['Instagram']['share'])->toBe(50.0)
        ->and($sources['Sem origem']['base'])->toBe(2)
        ->and($m->lossReasons())->toBe([['reason' => 'Contato não localizado', 'count' => 1, 'share' => 100.0]]);
});

it('compara com o período anterior', function () {
    opp($this->exec, OpportunityStage::Contact, createdAt: '2026-10-15');
    opp($this->exec, OpportunityStage::Contact, createdAt: '2026-11-05');
    opp($this->exec, OpportunityStage::Contact, createdAt: '2026-11-06');

    $m = new FunnelMetrics($this->period);

    expect($m->previous()->funnel()['base'])->toBe(1)
        ->and(FunnelMetrics::delta($m->funnel()['base'], $m->previous()->funnel()['base']))->toBe(100.0)
        ->and(FunnelMetrics::delta(5, 0))->toBeNull();
});

it('mostra no painel só os números do próprio executivo', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = $this->exec->user;
    $user->assignRole(Role::Executive->value);
    opp($this->exec, OpportunityStage::Contact);
    opp($this->other, OpportunityStage::Contact);
    opp($this->other, OpportunityStage::Contact);

    session([Period::SESSION_KEY => ['preset' => 'personalizado', 'start' => '2026-11-01', 'end' => '2026-11-30']]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertViewHas('funnel', fn ($f) => $f['base'] === 1)
        ->assertDontSee('Toda a equipe');
});
