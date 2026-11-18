<?php

use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\C2Snapshot;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    config(['services.c2.snapshot_token' => 'chave-do-painel']);
});

function snapshotPayload(array $metrics = []): array
{
    return ['collected_at' => now()->toIso8601String(), 'metrics' => $metrics + [
        'received_month' => 118133.68, 'received_prev_month' => 200011.7, 'receivable_open' => 794985.52,
        'deadlines_overdue' => 10, 'tasks_overdue' => 93, 'judicial_active' => 139, 'clients_new_month' => 34, 'clients_new_prev_month' => 69,
    ]];
}

it('recebe os totais do escritório com a chave certa', function () {
    $this->postJson(route('api.c2.snapshot'), snapshotPayload(), ['Authorization' => 'Bearer chave-do-painel'])
        ->assertCreated()->assertJson(['ok' => true, 'metrics' => 8]);

    expect(C2Snapshot::latest()->value('receivable_open'))->toBe(794985.52);
});

it('grava o horário enviado em UTC no fuso do sistema', function () {
    $this->travelTo(now()->setTime(15, 0));
    $payload = snapshotPayload();
    $payload['collected_at'] = now()->utc()->format('Y-m-d\TH:i:s\Z');

    $this->postJson(route('api.c2.snapshot'), $payload, ['Authorization' => 'Bearer chave-do-painel'])->assertCreated();

    expect(C2Snapshot::latest()->collected_at->diffInMinutes(now(), true))->toBeLessThan(1);
});

it('recusa chave errada, recebimento desligado e indicadores fora da lista', function () {
    $this->postJson(route('api.c2.snapshot'), snapshotPayload(), ['Authorization' => 'Bearer outra'])->assertUnauthorized();
    $this->postJson(route('api.c2.snapshot'), snapshotPayload())->assertUnauthorized();

    // Nada de texto ou dado pessoal: só os indicadores conhecidos, numéricos.
    $this->postJson(route('api.c2.snapshot'), snapshotPayload(['cpf_cliente' => 123]), ['Authorization' => 'Bearer chave-do-painel'])
        ->assertUnprocessable()->assertJsonPath('unknown', ['cpf_cliente']);
    $this->postJson(route('api.c2.snapshot'), snapshotPayload(['leads_month' => 'Maria']), ['Authorization' => 'Bearer chave-do-painel'])
        ->assertUnprocessable();

    config(['services.c2.snapshot_token' => null]);
    $this->postJson(route('api.c2.snapshot'), snapshotPayload(), ['Authorization' => 'Bearer chave-do-painel'])->assertStatus(503);

    expect(C2Snapshot::query()->count())->toBe(0);
});

it('apaga os envios mais antigos que o prazo de guarda', function () {
    C2Snapshot::query()->create(['metrics' => ['tasks_overdue' => 1], 'collected_at' => now()->subDays(C2Snapshot::KEEP_DAYS + 1)]);

    $this->postJson(route('api.c2.snapshot'), snapshotPayload(), ['Authorization' => 'Bearer chave-do-painel'])->assertCreated();

    expect(C2Snapshot::query()->count())->toBe(1);
});

it('mostra o escritório no dashboard; o financeiro só a quem vê custos', function () {
    C2Snapshot::query()->create(snapshotPayload());

    Livewire::actingAs(userWithRole(Role::Admin))->test(Dashboard::class)
        ->assertSee('Escritório (C2 Prev)')->assertSee('R$ 794.986')->assertSee('Tarefas atrasadas')->assertSee('139');

    $manager = userWithRole(Role::Manager);
    $manager->roles->first()->revokePermissionTo('finance.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Livewire::actingAs($manager->fresh())->test(Dashboard::class)
        ->assertSee('Escritório (C2 Prev)')->assertDontSee('A receber em aberto')->assertSee('Prazos atrasados');

    // Envio antigo, sem os indicadores de gestantes: o grupo não aparece.
    Livewire::actingAs(userWithRole(Role::Admin))->test(Dashboard::class)->assertDontSee('Gestantes (salário-maternidade)');

    C2Snapshot::query()->create(snapshotPayload(['pregnant_total' => 94, 'pregnant_entry_open' => 28, 'guides_pending' => 65]));
    Livewire::actingAs(userWithRole(Role::Admin))->test(Dashboard::class)
        ->assertSee('Gestantes (salário-maternidade)')->assertSee('Entrada liberada, sem requerimento')->assertSee('28');

    $executive = userWithRole(Role::Executive);
    Livewire::actingAs($executive)->test(Dashboard::class)->assertDontSee('Escritório (C2 Prev)');
});
