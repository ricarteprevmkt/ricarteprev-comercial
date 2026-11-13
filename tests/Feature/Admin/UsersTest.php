<?php

use App\Enums\Role;
use App\Livewire\Admin\Users;
use App\Models\Executive;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(Role::Admin);
});

it('cria usuário com perfil e envia link de senha quando a senha fica em branco', function () {
    Notification::fake();

    Livewire::actingAs($this->admin)->test(Users::class)
        ->set('form.name', 'Gestora Comercial')
        ->set('form.email', 'Gestora@RicartePrev.test')
        ->set('form.phone', '(88) 99876-5432')
        ->set('form.role', 'gestor')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'gestora@ricarteprev.test')->firstOrFail();
    expect($user->hasRole('gestor'))->toBeTrue()
        ->and($user->phone)->toBe('88998765432')
        ->and($user->email_verified_at)->not->toBeNull();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('cria usuário com senha definida pelo administrador, sem enviar link', function () {
    Notification::fake();

    Livewire::actingAs($this->admin)->test(Users::class)
        ->set('form.name', 'Executivo')
        ->set('form.email', 'exec@ricarteprev.test')
        ->set('form.role', 'executivo')
        ->set('form.password', 'senha-local-forte')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => 'exec@ricarteprev.test', 'password' => 'senha-local-forte']);
    $this->assertAuthenticated();
});

it('recusa e-mail repetido e telefone sem DDD', function () {
    User::factory()->create(['email' => 'repetido@ricarteprev.test']);

    Livewire::actingAs($this->admin)->test(Users::class)
        ->set('form.name', 'Outro')
        ->set('form.email', 'repetido@ricarteprev.test')
        ->set('form.phone', '99876-5432')
        ->call('save')
        ->assertHasErrors(['form.email', 'form.phone']);
});

it('troca o perfil e registra na auditoria', function () {
    $user = userWithRole(Role::Manager);

    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('edit', $user->id)
        ->set('form.role', 'executivo')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->getRoleNames()->all())->toBe(['executivo']);
    $this->assertDatabaseHas('activity_logs', ['event' => 'role_changed', 'subject_id' => $user->id]);
});

it('desativa e reativa o acesso de outro usuário', function () {
    $user = userWithRole(Role::Manager);

    $component = Livewire::actingAs($this->admin)->test(Users::class);
    $component->call('toggleActive', $user->id);
    expect($user->fresh()->active)->toBeFalse();

    $component->call('toggleActive', $user->id);
    expect($user->fresh()->active)->toBeTrue();
});

it('impede o administrador de se desativar ou remover o próprio perfil', function () {
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('edit', $this->admin->id)
        ->set('form.active', false)
        ->call('save')
        ->assertHasErrors('form.active');

    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('edit', $this->admin->id)
        ->set('form.role', 'gestor')
        ->call('save')
        ->assertHasErrors('form.role');

    expect($this->admin->fresh()->isAdmin())->toBeTrue()
        ->and($this->admin->fresh()->active)->toBeTrue();
});

it('mantém pelo menos um administrador ativo', function () {
    $other = userWithRole(Role::Admin);

    // Com dois administradores, um pode rebaixar o outro.
    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('edit', $other->id)->set('form.role', 'gestor')->call('save')->assertHasNoErrors();

    expect(User::role('administrador')->active()->count())->toBe(1);
});

it('não deixa trocar pelo cadastro de usuários o perfil de quem é executivo', function () {
    $executive = Executive::factory()->create();
    $executive->user->assignRole('executivo');

    Livewire::actingAs($this->admin)->test(Users::class)
        ->call('edit', $executive->user_id)
        ->set('form.role', 'gestor')
        ->call('save')
        ->assertHasErrors('form.role');
});

it('filtra por perfil e status', function () {
    userWithRole(Role::Manager, ['name' => 'Gestor Ativo']);
    userWithRole(Role::Indicator, ['name' => 'Indicador Inativo', 'active' => false]);

    Livewire::actingAs($this->admin)->test(Users::class)
        ->set('roleFilter', 'indicador')
        ->assertSee('Indicador Inativo')
        ->assertDontSee('Gestor Ativo')
        ->set('roleFilter', '')
        ->set('statusFilter', 'ativo')
        ->assertSee('Gestor Ativo')
        ->assertDontSee('Indicador Inativo');
});

it('nega a tela de usuários para quem não tem permissão', function () {
    $manager = userWithRole(Role::Manager);

    Livewire::actingAs($manager)->test(Users::class)->assertForbidden();
});
