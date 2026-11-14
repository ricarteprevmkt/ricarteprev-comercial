<?php

use App\Actions\Crm\RegisterContract;
use App\Actions\Notifications\SendAlerts;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Livewire\Reports\Index;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Region;
use App\Models\Visit;
use App\Notifications\SystemAlert;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $this->manager = userWithRole(Role::Manager);
    $this->region = Region::create(['name' => 'Cariri']);
    $this->darlan = Executive::factory()->create(['region_id' => $this->region->id]);
    $this->darlan->user->update(['name' => 'Darlan da Silva']);
    $this->darlan->user->assignRole('executivo');
    $this->savio = Executive::factory()->create();
    $this->savio->user->update(['name' => 'Sávio Lima']);

    Visit::factory()->count(3)->create(['executive_id' => $this->darlan->id, 'status' => VisitStatus::Done, 'scheduled_at' => '2026-11-10 09:00', 'contact_name' => 'Visita do Darlan']);
    Visit::factory()->create(['executive_id' => $this->savio->id, 'status' => VisitStatus::Done, 'scheduled_at' => '2026-11-10 09:00', 'contact_name' => 'Visita do Sávio']);

    $lead = Lead::factory()->create(['executive_id' => $this->darlan->id, 'name' => 'Cliente Contratado']);
    $o = Opportunity::factory()->create(['lead_id' => $lead->id, 'executive_id' => $this->darlan->id]);
    app(RegisterContract::class)->handle($o, ['status' => ContractStatus::Signed, 'signed_at' => '2026-11-12', 'contract_value' => '3500.00', 'number' => 'RP-0001'], null);

    $this->filters = fn (array $extra = [], $user = null) => ReportFilters::fromArray(['start' => '2026-11-01', 'end' => '2026-11-30'] + $extra, $user ?? $this->manager);
});

it('tem os 12 relatórios do escopo, todos gerando sem erro', function () {
    $reports = ReportRegistry::all();
    expect($reports)->toHaveCount(12);

    foreach ($reports as $report) {
        $rows = $report->rows(($this->filters)());
        $report->totals($rows, ($this->filters)());
        expect($report->columns())->not->toBeEmpty();
    }
});

it('filtra por executivo e por região', function () {
    $visits = ReportRegistry::find('visitas-realizadas');

    expect($visits->rows(($this->filters)()))->toHaveCount(4)
        ->and($visits->rows(($this->filters)(['executive' => $this->savio->id])))->toHaveCount(1)
        ->and($visits->rows(($this->filters)(['region' => $this->region->id])))->toHaveCount(3);
});

it('restringe o executivo aos próprios registros mesmo se pedir outro', function () {
    $visits = ReportRegistry::find('visitas-realizadas');
    $rows = $visits->rows(($this->filters)(['executive' => $this->savio->id], $this->darlan->user));

    expect($rows)->toBe([]);
    expect($visits->rows(($this->filters)([], $this->darlan->user)))->toHaveCount(3);
});

it('soma totais no relatório de contratos', function () {
    $report = ReportRegistry::find('contratos');
    $rows = $report->rows(($this->filters)());

    expect($rows[0]['number'])->toBe('RP-0001')
        ->and((float) $report->totals($rows, ($this->filters)())['value'])->toBe(3500.0);
});

it('exporta em PDF, Excel e CSV e registra na auditoria', function () {
    $query = ['start' => '2026-11-01', 'end' => '2026-11-30'];

    $pdf = $this->actingAs($this->manager)->get(route('reports.export', ['report' => 'contratos', 'format' => 'pdf'] + $query));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');

    $xlsx = $this->actingAs($this->manager)->get(route('reports.export', ['report' => 'contratos', 'format' => 'xlsx'] + $query));
    $xlsx->assertOk();
    expect($xlsx->headers->get('content-disposition'))->toContain('.xlsx');

    $csv = $this->actingAs($this->manager)->get(route('reports.export', ['report' => 'visitas-realizadas', 'format' => 'csv'] + $query));
    $csv->assertOk();
    $content = $csv->streamedContent();
    expect($content)->toStartWith("\u{FEFF}")
        ->toContain('Data;Executivo;Contato')
        ->toContain('Darlan da Silva');

    $this->assertDatabaseHas('activity_logs', ['log_name' => 'relatorios', 'event' => 'export', 'causer_id' => $this->manager->id]);
});

it('nega exportação sem permissão e relatório inexistente', function () {
    $this->actingAs($this->darlan->user)->get(route('reports.export', ['report' => 'contratos', 'format' => 'pdf']))->assertForbidden();
    $this->actingAs($this->manager)->get(route('reports.export', ['report' => 'nao-existe', 'format' => 'pdf']))->assertNotFound();
    $this->actingAs($this->manager)->get(route('reports.export', ['report' => 'contratos', 'format' => 'docx']))->assertNotFound();
});

it('mostra a prévia na tela com filtros', function () {
    session([Period::SESSION_KEY => ['preset' => 'personalizado', 'start' => '2026-11-01', 'end' => '2026-11-30']]);

    Livewire::actingAs($this->manager)->test(Index::class)
        ->call('select', 'visitas-realizadas')
        ->assertSee('Visita do Darlan')
        ->set('filters.executive', (string) $this->savio->id)
        ->assertSee('Visita do Sávio')
        ->assertDontSee('Visita do Darlan');
});

it('envia avisos de visita próxima e o resumo diário, sem repetir no mesmo dia', function () {
    Visit::factory()->create(['executive_id' => $this->darlan->id, 'scheduled_at' => now()->addMinutes(40), 'contact_name' => 'Maria']);
    Visit::factory()->create(['executive_id' => $this->darlan->id, 'scheduled_at' => now()->subDays(3)]); // sem resultado

    $alerts = app(SendAlerts::class);
    expect($alerts->upcomingVisits())->toBe(1)
        ->and($alerts->upcomingVisits())->toBe(0);

    $first = $alerts->daily();
    expect($first)->toBeGreaterThan(0)
        ->and($alerts->daily())->toBe(0);

    $messages = $this->darlan->user->notifications()->get()->map(fn ($n) => $n->data['message'])->all();
    expect(collect($messages)->contains(fn ($m) => str_contains($m, 'Maria')))->toBeTrue()
        ->and(collect($messages)->contains(fn ($m) => str_contains($m, 'sem resultado registrado')))->toBeTrue()
        ->and($this->darlan->user->notifications()->first()->type)->toBe(SystemAlert::class);
});
