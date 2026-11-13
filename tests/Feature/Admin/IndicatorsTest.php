<?php

use App\Enums\Role;
use App\Livewire\Referrals\CommissionRules;
use App\Livewire\Referrals\Indicators;
use App\Models\City;
use App\Models\CommissionRule;
use App\Models\RegionalIndicator;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->manager = userWithRole(Role::Manager);
    $this->seed(CitySeeder::class);
    $this->crato = City::where('ibge_code', 2304202)->firstOrFail();
    $this->crato->update(['served' => true]);
});

function fillIndicator($component, array $overrides = [])
{
    $data = array_merge([
        'name' => 'João Alves', 'whatsapp' => '(88) 99111-2222', 'city_id' => test()->crato->id,
        'neighborhood' => 'Centro', 'registered_at' => now()->toDateString(), 'status' => 'active',
    ], $overrides);

    foreach ($data as $field => $value) {
        $component->set("form.{$field}", $value);
    }

    return $component;
}

it('cadastra indicador sem acesso ao sistema', function () {
    fillIndicator(Livewire::actingAs($this->manager)->test(Indicators::class))->call('save')->assertHasNoErrors();

    $indicator = RegionalIndicator::firstOrFail();
    expect($indicator->whatsapp)->toBe('88991112222')
        ->and($indicator->user_id)->toBeNull()
        ->and($indicator->commission_rule_id)->toBeNull();
});

it('dá acesso ao indicador com perfil indicador e exige e-mail', function () {
    fillIndicator(Livewire::actingAs($this->manager)->test(Indicators::class))
        ->set('hasAccess', true)
        ->call('save')
        ->assertHasErrors('form.email');

    fillIndicator(Livewire::actingAs($this->manager)->test(Indicators::class), ['email' => 'joao@exemplo.test'])
        ->set('hasAccess', true)
        ->call('save')
        ->assertHasNoErrors();

    $indicator = RegionalIndicator::with('user')->firstOrFail();
    expect($indicator->user->hasRole('indicador'))->toBeTrue()
        ->and($indicator->hasActiveAccess())->toBeTrue();
});

it('inativar o indicador ou retirar o acesso desativa o login', function () {
    fillIndicator(Livewire::actingAs($this->manager)->test(Indicators::class), ['email' => 'joao@exemplo.test'])
        ->set('hasAccess', true)->call('save');
    $indicator = RegionalIndicator::firstOrFail();

    Livewire::actingAs($this->manager)->test(Indicators::class)
        ->call('edit', $indicator->id)->set('form.status', 'inactive')->call('save')->assertHasNoErrors();
    expect($indicator->user->fresh()->active)->toBeFalse();

    Livewire::actingAs($this->manager)->test(Indicators::class)
        ->call('edit', $indicator->id)->set('form.status', 'active')->set('hasAccess', false)->call('save');
    expect($indicator->user->fresh()->active)->toBeFalse();
});

it('não aceita dois indicadores com o mesmo WhatsApp', function () {
    fillIndicator(Livewire::actingAs($this->manager)->test(Indicators::class))->call('save');

    fillIndicator(Livewire::actingAs($this->manager)->test(Indicators::class), ['name' => 'Outro', 'whatsapp' => '88 99111 2222'])
        ->call('save')
        ->assertHasErrors('form.whatsapp');

    expect(RegionalIndicator::count())->toBe(1);
});

it('associa uma regra de comissão ao indicador', function () {
    $rule = CommissionRule::create(['name' => 'Padrão', 'type' => 'fixed', 'value' => '150.00', 'trigger_event' => 'contract_signed']);

    fillIndicator(Livewire::actingAs($this->manager)->test(Indicators::class), ['commission_rule_id' => $rule->id])
        ->call('save')->assertHasNoErrors();

    expect(RegionalIndicator::first()->commissionRule->is($rule))->toBeTrue();
});

it('cria regra percentual exigindo base de cálculo e limite de 100%', function () {
    Livewire::actingAs($this->manager)->test(CommissionRules::class)
        ->set('form.name', 'Percentual')
        ->set('form.type', 'percent')
        ->set('form.value', '120')
        ->call('save')
        ->assertHasErrors(['form.value', 'form.base']);

    Livewire::actingAs($this->manager)->test(CommissionRules::class)
        ->set('form.name', 'Percentual')
        ->set('form.type', 'percent')
        ->set('form.value', '5')
        ->set('form.base', 'received_revenue')
        ->set('form.trigger_event', 'revenue_received')
        ->call('save')
        ->assertHasNoErrors();

    $rule = CommissionRule::firstOrFail();
    expect($rule->value)->toBe('5.00')
        ->and($rule->summary())->toBe('5,00% sobre receita recebida, No recebimento da receita');
});

it('descarta a base de cálculo em regra de valor fixo', function () {
    Livewire::actingAs($this->manager)->test(CommissionRules::class)
        ->set('form.name', 'Fixa')
        ->set('form.type', 'fixed')
        ->set('form.value', '200')
        ->set('form.base', 'contract_value')
        ->call('save')
        ->assertHasNoErrors();

    expect(CommissionRule::first()->base)->toBeNull();
});

it('nega o cadastro de indicadores ao executivo', function () {
    $exec = userWithRole(Role::Executive);

    Livewire::actingAs($exec)->test(Indicators::class)->assertForbidden();
    Livewire::actingAs($exec)->test(CommissionRules::class)->assertForbidden();
    expect(User::count())->toBeGreaterThan(1);
});
