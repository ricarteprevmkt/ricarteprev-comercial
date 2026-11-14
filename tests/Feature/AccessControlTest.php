<?php

use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

it('manda visitantes para o login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('não oferece cadastro público', function () {
    $this->get('/register')->assertNotFound();
});

it('libera todos os módulos para o administrador', function () {
    $admin = userWithRole(Role::Admin);

    foreach (['dashboard', 'executives.index', 'agenda.index', 'funnel.index', 'finance.index', 'referrals.index', 'reports.index', 'admin.users', 'admin.roles', 'admin.audit'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
});

it('impede o executivo de abrir áreas administrativas e de equipe', function () {
    $exec = userWithRole(Role::Executive);

    $this->actingAs($exec)->get(route('admin.users'))->assertForbidden();
    $this->actingAs($exec)->get(route('admin.settings'))->assertForbidden();
    $this->actingAs($exec)->get(route('executives.index'))->assertForbidden();
    $this->actingAs($exec)->get(route('reports.index'))->assertForbidden();

    $this->actingAs($exec)->get(route('agenda.index'))->assertOk();
    $this->actingAs($exec)->get(route('funnel.index'))->assertOk();
});

it('mostra ao executivo só os itens de menu permitidos', function () {
    $exec = userWithRole(Role::Executive);

    $this->actingAs($exec)->get(route('agenda.index'))
        ->assertSee('Agenda e rotas')
        ->assertSee('Funil comercial')
        ->assertDontSee('Perfis e permissões')
        ->assertDontSee('Auditoria');
});

it('leva cada perfil ao primeiro módulo que ele pode abrir', function (Role $role, string $expected) {
    $user = userWithRole($role);

    $this->actingAs($user)->get(route('start'))->assertRedirect(route($expected));
})->with([
    'administrador' => [Role::Admin, 'dashboard'],
    'gestor' => [Role::Manager, 'dashboard'],
    'executivo' => [Role::Executive, 'dashboard'],
    'indicador' => [Role::Indicator, 'referrals.mine'],
]);

it('restringe o indicador às próprias indicações', function () {
    $indicator = userWithRole(Role::Indicator);

    $this->actingAs($indicator)->get(route('referrals.mine'))->assertOk();
    $this->actingAs($indicator)->get(route('referrals.index'))->assertForbidden();
    $this->actingAs($indicator)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($indicator)->get(route('finance.index'))->assertForbidden();
    $this->actingAs($indicator)->get(route('funnel.index'))->assertForbidden();
});

it('aplica na hora uma permissão concedida pelo administrador ao perfil', function () {
    $indicator = userWithRole(Role::Indicator);
    $this->actingAs($indicator)->get(route('reports.index'))->assertForbidden();

    RoleModel::findByName(Role::Indicator->value)->givePermissionTo('reports.view');

    $this->actingAs($indicator->fresh())->get(route('reports.index'))->assertOk();
});

it('bloqueia o login de usuário desativado', function () {
    $user = User::factory()->create(['active' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('encerra a sessão de quem é desativado enquanto está conectado', function () {
    $user = userWithRole(Role::Manager);
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->update(['active' => false]);

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('registra login e data do último acesso', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
    $this->assertDatabaseHas('activity_logs', ['log_name' => 'acesso', 'event' => 'login', 'causer_id' => $user->id]);
});

it('registra tentativa de login sem sucesso', function () {
    $this->post(route('login.store'), ['email' => 'ninguem@ricarteprev.test', 'password' => 'errada']);

    $this->assertDatabaseHas('activity_logs', ['log_name' => 'acesso', 'event' => 'login_failed']);
});

it('mostra a cada perfil a entrada certa de indicações no menu', function () {
    $this->actingAs(userWithRole(Role::Admin))->get(route('admin.users'))
        ->assertSee('Indicadores regionais')->assertDontSee('Minhas indicações');

    $this->actingAs(userWithRole(Role::Indicator))->get(route('referrals.mine'))
        ->assertSee('Minhas indicações');
});
