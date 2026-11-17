<?php

use App\Actions\C2\SyncC2Processes;
use App\Actions\Crm\RegisterContract;
use App\Actions\Notifications\SendAlerts;
use App\Enums\ContractStatus;
use App\Enums\InssOutcome;
use App\Enums\Role;
use App\Livewire\Admin\C2Integration;
use App\Livewire\Funnel\Contracts;
use App\Livewire\Funnel\Dashboard;
use App\Models\C2Process;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\IntegrationState;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\C2\C2Client;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const CPF_MARIA = '52998224725';
const CPF_JOSE = '11144477735';

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    config(['services.c2.key' => 'chave-de-teste', 'services.c2.url' => 'https://c2.test']);
    $this->exec = Executive::factory()->create();
});

function c2Lead(Executive $exec, string $name, ?string $cpf): Lead
{
    $lead = Lead::factory()->create(['executive_id' => $exec->id, 'name' => $name]);
    $lead->setDocument($cpf);
    $lead->save();

    return $lead;
}

function c2Contract(Lead $lead, string $signedAt): Contract
{
    $o = Opportunity::factory()->create(['lead_id' => $lead->id, 'executive_id' => $lead->executive_id]);

    return app(RegisterContract::class)->handle($o, ['status' => ContractStatus::Signed, 'signed_at' => $signedAt, 'contract_value' => '3000.00'], null);
}

/** @return array<string, mixed> */
function c2ProcessJson(string $id, string $clientId, string $name, ?string $cpf, ?string $decision = null, ?string $status = 'Em análise', ?string $concludedAt = null): array
{
    return [
        'id' => $id, 'source' => 'meu_inss',
        'client' => ['id' => $clientId, 'name' => $name, 'cpf' => $cpf],
        'request_number' => '123456', 'benefit_number' => null, 'service' => 'Aposentadoria', 'benefit_type' => 'Aposentadoria por idade',
        'source_status' => $status, 'classified_status' => $status,
        'created_at' => '2026-10-01T10:00:00Z', 'updated_at' => '2026-11-01T10:00:00Z',
        'opened_at' => '2026-10-01T10:00:00Z', 'concluded_at' => $concludedAt, 'last_checked_at' => null,
        'requirement' => ['description' => null, 'deadline' => null],
        'appointment' => ['date' => null, 'time' => null, 'location' => null],
        'decision' => ['status' => $decision, 'reason' => null, 'appeal_deadline' => null],
    ];
}

function fakeC2(array $events = []): void
{
    Http::fake([
        'c2.test/v1/monitoramentos*' => Http::response(['items' => [
            ['id' => 'm1', 'process_id' => 'p1', 'client_id' => 'c1'],
            ['id' => 'm2', 'process_id' => 'p2', 'client_id' => 'c2'],
        ], 'next_cursor' => 'mon-1', 'has_more' => false]),
        'c2.test/v1/eventos*' => Http::response(['items' => $events, 'next_cursor' => 'evt-1', 'has_more' => false]),
        'c2.test/v1/processos/p1' => Http::response(c2ProcessJson('p1', 'c1', 'Maria Souza', '529.982.247-25', 'Deferido', 'Concluído', '2026-11-10T00:00:00Z')),
        'c2.test/v1/processos/p2' => Http::response(c2ProcessJson('p2', 'c2', 'José Lima', CPF_JOSE)),
    ]);
}

it('classifica a situação em texto livre do C2', function () {
    expect(InssOutcome::classify('Indeferido', null, true))->toBe(InssOutcome::Denied)
        ->and(InssOutcome::classify('DEFERIDO', null, true))->toBe(InssOutcome::Granted)
        ->and(InssOutcome::classify(null, 'Benefício concedido', true))->toBe(InssOutcome::Granted)
        ->and(InssOutcome::classify(null, 'Em análise', false))->toBe(InssOutcome::InProgress)
        ->and(InssOutcome::classify(null, 'Cancelado', true))->toBe(InssOutcome::Concluded);
});

it('faz a carga inicial, lê o feed e liga os processos aos contatos pelo CPF', function () {
    $maria = c2Lead($this->exec, 'Maria Souza', CPF_MARIA);
    fakeC2([['event_id' => 'e1', 'type' => 'movement.created', 'process_id' => 'p1']]);

    $result = (new SyncC2Processes(new C2Client))->run();

    expect($result['error'])->toBeNull()
        ->and($result['backfill_done'])->toBeTrue()
        ->and(IntegrationState::get(SyncC2Processes::EVENTS_CURSOR))->toBe('evt-1');

    $p1 = C2Process::query()->where('c2_id', 'p1')->sole();
    expect($p1->lead_id)->toBe($maria->id)
        ->and($p1->outcome)->toBe(InssOutcome::Granted)
        ->and($p1->cpf_last_digits)->toBe('25')
        ->and($p1->concluded_at->toDateString())->toBe('2026-11-10');

    // O CPF não é guardado em claro.
    expect(C2Process::query()->where('cpf_hash', 'like', '%'.CPF_MARIA.'%')->exists())->toBeFalse()
        ->and(C2Process::query()->where('c2_id', 'p2')->value('lead_id'))->toBeNull();

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer chave-de-teste'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/eventos') && $r['source'] === 'meu_inss');
});

it('liga depois o processo quando o CPF do contato é cadastrado', function () {
    fakeC2();
    (new SyncC2Processes(new C2Client))->run();
    expect(C2Process::query()->where('c2_id', 'p2')->value('lead_id'))->toBeNull();

    $jose = c2Lead($this->exec, 'José Lima', CPF_JOSE);
    $result = (new SyncC2Processes(new C2Client))->run();

    expect($result['linked'])->toBe(1)
        ->and(C2Process::query()->where('c2_id', 'p2')->value('lead_id'))->toBe($jose->id);
});

it('respeita a cota de consultas por rodada e continua na seguinte', function () {
    fakeC2();

    $first = (new SyncC2Processes(new C2Client, budget: 2))->run();
    expect($first['backfill_done'])->toBeFalse()
        ->and($first['synced'])->toBe(1)
        ->and(IntegrationState::get(SyncC2Processes::BACKFILL_CURSOR))->toBeNull();

    $second = (new SyncC2Processes(new C2Client, budget: 10))->run();
    expect($second['backfill_done'])->toBeTrue()
        ->and(C2Process::query()->count())->toBe(2);
    // p1 já lido na carga: não é consultado de novo ao reler a página.
    // Rodada 1: página + p1. Rodada 2: página + p2 + feed.
    Http::assertSentCount(2 + 3);
});

it('recomeça o feed quando o cursor expira', function () {
    IntegrationState::put(SyncC2Processes::BACKFILL_DONE, true);
    IntegrationState::put(SyncC2Processes::EVENTS_CURSOR, 'velho');
    Http::fake(['c2.test/v1/eventos*' => Http::response(['code' => 'CURSOR_EXPIRED'], 410)]);

    $result = (new SyncC2Processes(new C2Client))->run();

    expect($result['error'])->toBeNull()
        ->and(IntegrationState::get(SyncC2Processes::EVENTS_CURSOR))->toBeNull();
});

it('registra a falha de autenticação sem quebrar o agendador', function () {
    Http::fake(['c2.test/*' => Http::response(['code' => 'UNAUTHORIZED'], 401)]);

    $this->artisan('app:c2-sync')->expectsOutputToContain('Chave do C2 ausente ou inválida')->assertFailed();
    expect(IntegrationState::get(SyncC2Processes::LAST_ERROR)['message'])->toContain('Chave do C2');
});

it('não consulta nada sem a chave no .env', function () {
    config(['services.c2.key' => null]);
    Http::fake();

    $this->artisan('app:c2-sync')->expectsOutputToContain('desligada')->assertSuccessful();
    Http::assertNothingSent();
});

it('confere a chave e as permissões com --check', function () {
    Http::fake(['c2.test/v1/me' => Http::response(['account_id' => 'a1', 'sources' => ['meu_inss'], 'permissions' => ['events:read', 'processes:read', 'monitoring:read'], 'limits' => []])]);

    $this->artisan('app:c2-sync --check')->expectsOutputToContain('Conexão com o C2 funcionando')->assertSuccessful();
});

it('avisa sobre contrato assinado há mais de 2 dias sem cadastro no C2', function () {
    $manager = userWithRole(Role::Manager);
    $sem = c2Lead($this->exec, 'Cliente Esquecido', CPF_JOSE);
    c2Contract($sem, '2026-11-17');
    $com = c2Lead($this->exec, 'Maria Souza', CPF_MARIA);
    c2Contract($com, '2026-11-17');
    c2Contract(c2Lead($this->exec, 'Assinou Ontem', null), '2026-11-19');

    // Antes da carga inicial, não avisa (todos apareceriam como ausentes).
    app(SendAlerts::class)->daily();
    expect($manager->notifications()->where('data->key', 'like', 'team-missing-in-c2%')->exists())->toBeFalse();

    fakeC2();
    (new SyncC2Processes(new C2Client))->run();
    C2Process::query()->where('c2_id', 'p2')->delete(); // José ainda não está no C2

    app(SendAlerts::class)->daily();

    $alert = $manager->notifications()->where('data->key', 'like', 'team-missing-in-c2%')->first();
    expect($alert->data['message'])->toStartWith('1 contrato(s) assinado(s) há mais de 2 dia(s) sem cadastro no C2')
        ->and($this->exec->user->notifications()->where('data->key', 'like', 'missing-in-c2%')->exists())->toBeTrue();
});

it('mostra o resultado no INSS nos contratos e a taxa de deferimento no painel', function () {
    $admin = userWithRole(Role::Admin);
    c2Contract(c2Lead($this->exec, 'Maria Souza', CPF_MARIA), '2026-11-05');
    c2Contract(c2Lead($this->exec, 'José Lima', CPF_JOSE), '2026-11-06');
    c2Contract(c2Lead($this->exec, 'Cliente Esquecido', null), '2026-11-07');
    fakeC2();
    (new SyncC2Processes(new C2Client))->run();
    C2Process::query()->where('c2_id', 'p2')->update(['outcome' => InssOutcome::Denied->value]);

    Livewire::actingAs($admin)->test(Contracts::class)
        ->assertSee('Deferido')->assertSee('Indeferido')->assertSee('Sem cadastro no C2')
        ->set('inssFilter', 'sem-c2')
        ->assertSee('Cliente Esquecido')->assertDontSee('Maria Souza');

    $this->actingAs($admin)->withSession(['period' => ['preset' => 'mes_atual']]);
    Livewire::actingAs($admin)->test(Dashboard::class)
        ->assertSee('Resultado no INSS')
        ->assertSee('50,0%')
        ->set('inssGroup', 'origem')
        ->assertSee('Por origem');
});

it('esconde o resultado no INSS quando a integração está desligada', function () {
    config(['services.c2.key' => null]);
    $admin = userWithRole(Role::Admin);
    c2Contract(c2Lead($this->exec, 'Maria Souza', CPF_MARIA), '2026-11-05');

    Livewire::actingAs($admin)->test(Dashboard::class)->assertDontSee('Resultado no INSS');
    Livewire::actingAs($admin)->test(Contracts::class)->assertDontSee('Sem cadastro no C2');
});

it('lista no painel da integração os clientes do C2 sem contato', function () {
    fakeC2();
    (new SyncC2Processes(new C2Client))->run();

    $this->actingAs(userWithRole(Role::Admin))->get(route('admin.c2'))->assertOk();
    Livewire::actingAs(userWithRole(Role::Admin))->test(C2Integration::class)
        ->assertSee('Maria Souza')->assertSee('***.***.***-25')->assertSee('José Lima');

    $this->actingAs(userWithRole(Role::Executive))->get(route('admin.c2'))->assertForbidden();
});
