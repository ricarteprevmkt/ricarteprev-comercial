<?php

use App\Actions\C2\RefreshC2Scope;
use App\Actions\C2\SyncC2Processes;
use App\Actions\Crm\RegisterContract;
use App\Enums\ContractStatus;
use App\Enums\InssOutcome;
use App\Enums\Role;
use App\Livewire\Administrative\Dashboard;
use App\Livewire\Administrative\Deadlines;
use App\Livewire\Administrative\Pending;
use App\Livewire\Administrative\Processes;
use App\Livewire\Administrative\ProcessShow;
use App\Metrics\InssDeadlines;
use App\Models\C2Process;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\IntegrationState;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\C2\C2Client;
use App\Support\Cpf;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    config(['services.c2.key' => 'chave-de-teste', 'services.c2.url' => 'https://c2.test']);
    $this->exec = Executive::factory()->create();
});

function admLead(Executive $exec, string $name, string $cpf): Lead
{
    $lead = Lead::factory()->create(['executive_id' => $exec->id, 'name' => $name]);
    $lead->setDocument($cpf);
    $lead->save();

    return $lead;
}

function admContract(Lead $lead, string $signedAt): Contract
{
    $o = Opportunity::factory()->create(['lead_id' => $lead->id, 'executive_id' => $lead->executive_id]);

    return app(RegisterContract::class)->handle($o, ['status' => ContractStatus::Signed, 'signed_at' => $signedAt, 'contract_value' => '3000.00'], null);
}

/** @param  array<string, mixed>  $attributes */
function admProcess(array $attributes): C2Process
{
    static $n = 0;
    $n++;

    return C2Process::query()->create($attributes + [
        'c2_id' => "p{$n}", 'c2_client_id' => "c{$n}", 'client_name' => "Cliente {$n}",
        'outcome' => InssOutcome::InProgress, 'benefit_type' => 'Aposentadoria por idade', 'synced_at' => now(),
    ]);
}

it('guarda exigência, agendamento, decisão e o estado do monitoramento lidos do C2', function () {
    Http::fake([
        'c2.test/v1/monitoramentos*' => Http::response(['items' => [
            ['id' => 'm1', 'process_id' => 'p1', 'client_id' => 'c1', 'state' => 'action_required', 'action_required' => 'password_invalid'],
        ], 'next_cursor' => null, 'has_more' => false]),
        'c2.test/v1/eventos*' => Http::response(['items' => [], 'next_cursor' => 'e', 'has_more' => false]),
        'c2.test/v1/processos/p1' => Http::response([
            'id' => 'p1', 'source' => 'meu_inss', 'client' => ['id' => 'c1', 'name' => 'Maria Souza', 'cpf' => '52998224725'],
            'request_number' => '987654', 'benefit_number' => '1234567890', 'service' => 'Aposentadoria', 'benefit_type' => 'Aposentadoria por idade',
            'source_status' => 'Exigência', 'classified_status' => 'Em exigência', 'created_at' => '2026-11-01', 'updated_at' => '2026-11-15',
            'opened_at' => '2026-11-01', 'concluded_at' => null, 'last_checked_at' => '2026-11-19T21:00:00Z',
            'requirement' => ['description' => 'Apresentar CTPS original', 'deadline' => '25/11/2026'],
            'appointment' => ['date' => '2026-11-27', 'time' => '09:30', 'location' => 'APS Centro'],
            'decision' => ['status' => null, 'reason' => null, 'appeal_deadline' => null],
        ]),
    ]);

    (new SyncC2Processes(new C2Client))->run();

    $p = C2Process::query()->sole();
    expect($p->requirement_text)->toBe('Apresentar CTPS original')
        ->and($p->requirement_deadline->toDateString())->toBe('2026-11-25')
        ->and($p->appointment_date->toDateString())->toBe('2026-11-27')
        ->and($p->appointment_time)->toBe('09:30')
        ->and($p->benefit_number)->toBe('1234567890')
        ->and($p->monitoring_state)->toBe('action_required')
        ->and($p->monitoringLabel())->toBe('Senha do gov.br inválida');
});

it('atualiza o monitoramento pelos eventos do feed', function () {
    $p = admProcess(['c2_id' => 'px']);
    IntegrationState::put(SyncC2Processes::BACKFILL_DONE, true);
    Http::fake([
        'c2.test/v1/eventos*' => Http::sequence()
            ->push(['items' => [['event_id' => 'e1', 'type' => 'monitoring.action_required', 'process_id' => 'px']], 'next_cursor' => 'a', 'has_more' => false])
            ->push(['items' => [['event_id' => 'e2', 'type' => 'monitoring.recovered', 'process_id' => 'px']], 'next_cursor' => 'b', 'has_more' => false]),
        'c2.test/v1/monitoramentos*' => Http::response(['items' => [['id' => 'm', 'process_id' => 'px', 'state' => 'action_required', 'action_required' => 'authentication_required']], 'next_cursor' => null, 'has_more' => false]),
    ]);

    (new SyncC2Processes(new C2Client))->run();
    expect($p->fresh()->monitoring_action)->toBe('authentication_required');

    (new SyncC2Processes(new C2Client))->run();
    expect($p->fresh()->monitoring_state)->toBe('active')->and($p->fresh()->monitoring_action)->toBeNull();
});

it('deixa fora do escopo os protocolos antigos do cliente', function () {
    $maria = admLead($this->exec, 'Maria Souza', '52998224725');
    $contract = admContract($maria, '2026-11-01');
    $old = admProcess(['lead_id' => $maria->id, 'opened_at' => '2024-03-01', 'outcome' => InssOutcome::Denied]);
    $tolerated = admProcess(['lead_id' => $maria->id, 'opened_at' => '2026-10-10']);
    $new = admProcess(['lead_id' => $maria->id, 'opened_at' => '2026-11-05']);
    // Cliente sem contrato no sistema: conta o que está em andamento ou é recente.
    $oldDone = admProcess(['opened_at' => '2023-01-01', 'outcome' => InssOutcome::Granted]);
    $oldRunning = admProcess(['opened_at' => '2023-01-01']);

    app(RefreshC2Scope::class)->handle();

    expect($old->fresh()->in_scope)->toBeFalse()
        ->and($tolerated->fresh()->in_scope)->toBeTrue()
        ->and($new->fresh()->in_scope)->toBeTrue()
        ->and($oldDone->fresh()->in_scope)->toBeFalse()
        ->and($oldRunning->fresh()->in_scope)->toBeTrue()
        ->and($contract->fresh()->c2Processes)->toHaveCount(2);

    // Só o protocolo antigo não conta como cadastro no C2.
    C2Process::query()->whereKey([$tolerated->id, $new->id])->delete();
    expect(Contract::query()->missingInC2(2)->pluck('id')->all())->toBe([$contract->id]);
});

it('monta a agenda de prazos com exigências, agendamentos e recursos', function () {
    admProcess(['client_name' => 'Exigência Vencida', 'requirement_text' => 'CTPS', 'requirement_deadline' => '2026-11-18']);
    admProcess(['client_name' => 'Perícia Marcada', 'appointment_date' => '2026-11-22', 'appointment_time' => '10:00']);
    admProcess(['client_name' => 'Recurso Correndo', 'outcome' => InssOutcome::Denied, 'appeal_deadline' => '2026-12-10']);
    admProcess(['client_name' => 'Antigo', 'requirement_deadline' => '2026-11-21', 'in_scope' => false]);

    $items = (new InssDeadlines)->list(30);

    expect($items->pluck('process.client_name')->all())->toBe(['Exigência Vencida', 'Perícia Marcada', 'Recurso Correndo'])
        ->and($items[0]['days'])->toBe(-2)
        ->and(InssDeadlines::when(-2))->toBe('venceu há 2 dias');

    Livewire::actingAs(userWithRole(Role::Administrative))->test(Deadlines::class)
        ->assertSee('Perícia Marcada')->assertSee('10:00')
        ->set('type', 'recurso')
        ->assertSee('Recurso Correndo')->assertDontSee('Perícia Marcada');
});

it('mostra o painel, os processos e as pendências ao perfil administrativo', function () {
    $user = userWithRole(Role::Administrative);
    admProcess(['client_name' => 'Ana Paula', 'requirement_text' => 'Laudo', 'requirement_deadline' => '2026-11-15']);
    admProcess(['client_name' => 'Bruno Dias', 'monitoring_state' => 'action_required', 'monitoring_action' => 'password_invalid']);
    admProcess(['client_name' => 'Carla Antiga', 'in_scope' => false, 'outcome' => InssOutcome::Denied]);

    foreach (['adm.index', 'adm.processes', 'adm.deadlines', 'adm.pending'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }

    Livewire::actingAs($user)->test(Dashboard::class)->assertSee('Exigências abertas')->assertSee('Ana Paula');
    Livewire::actingAs($user)->test(Processes::class)
        ->assertSee('Ana Paula')->assertDontSee('Carla Antiga')
        ->set('scope', 'fora')->assertSee('Carla Antiga')->assertDontSee('Ana Paula');
    Livewire::actingAs($user)->test(Processes::class)->set('flag', 'monitoramento')
        ->assertSee('Bruno Dias')->assertDontSee('Ana Paula');
    Livewire::actingAs($user)->test(Pending::class)
        ->assertSee('Senha do gov.br inválida')->assertSee('venceu em 15/11/2026');

    expect(Navigation::homeRouteFor($user))->toBe('adm.index')
        ->and(collect(Navigation::for($user))->pluck('group')->all())->toBe(['Administrativo']);
});

it('consulta as movimentações no C2 na ficha do processo', function () {
    $lead = admLead($this->exec, 'Maria Souza', '52998224725');
    admContract($lead, '2026-11-01');
    $p = admProcess(['client_name' => 'Maria Souza', 'lead_id' => $lead->id, 'cpf_hash' => Cpf::hash('52998224725'), 'cpf_last_digits' => '25']);
    Http::fake(['c2.test/v1/movimentacoes*' => Http::response(['items' => [
        ['id' => 'mv1', 'kind' => 'status', 'title' => 'Exigência emitida', 'occurred_at' => '2026-11-10T12:00:00Z', 'summary' => 'Apresentar documentos'],
    ], 'next_cursor' => null, 'has_more' => false])]);

    $user = userWithRole(Role::Administrative);
    $this->actingAs($user)->get(route('adm.processes.show', $p))->assertOk();
    Livewire::actingAs($user)->test(ProcessShow::class, ['process' => $p])
        ->assertSee('Exigência emitida')->assertSee('10/11/2026')
        ->assertSee('***.***.***-25')->assertSee('Contrato assinado em')
        ->assertDontSee('Abrir no funil')
        ->assertSeeHtml('href="https://c2prev.compute.capital/clientes/'.$p->c2_client_id.'"');
});

it('nega o administrativo a quem não tem a permissão', function () {
    $this->actingAs(userWithRole(Role::Executive))->get(route('adm.index'))->assertForbidden();
});
