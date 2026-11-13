<?php

use App\Enums\Role;
use App\Livewire\Executives\Index;
use App\Models\City;
use App\Models\Executive;
use App\Models\Goal;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CitySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->admin = userWithRole(Role::Admin);
    $this->seed(CitySeeder::class);
    $this->region = Region::create(['name' => 'Cariri']);
    $this->juazeiro = City::where('ibge_code', 2307304)->firstOrFail();
    $this->juazeiro->update(['served' => true, 'region_id' => $this->region->id]);
});

function fillExecutive($component, array $overrides = [])
{
    $data = array_merge([
        'name' => 'Darlan da Silva', 'email' => 'darlan@ricarteprev.test', 'phone' => '(88) 99876-5432',
        'cpf' => '529.982.247-25', 'hired_at' => '2025-03-10', 'status' => 'active',
        'monthly_visits_target' => 60, 'monthly_contracts_target' => 12, 'cost_allowance' => '450.00',
    ], $overrides);

    foreach ($data as $field => $value) {
        $component->set("form.{$field}", $value);
    }

    return $component;
}

it('cadastra executivo criando o acesso com perfil executivo e sugerindo a região da cidade', function () {
    $component = Livewire::actingAs($this->admin)->test(Index::class)
        ->set('form.city_id', $this->juazeiro->id);

    expect($component->get('form.region_id'))->toBe($this->region->id);

    fillExecutive($component)->call('save')->assertHasNoErrors();

    $executive = Executive::with('user')->firstOrFail();
    expect($executive->user->email)->toBe('darlan@ricarteprev.test')
        ->and($executive->user->hasRole('executivo'))->toBeTrue()
        ->and($executive->user->active)->toBeTrue()
        ->and($executive->city_id)->toBe($this->juazeiro->id)
        ->and($executive->region_id)->toBe($this->region->id)
        ->and($executive->cost_allowance)->toBe('450.00')
        ->and($executive->cpf())->toBe('52998224725');
});

it('guarda o CPF criptografado no banco e fora da auditoria', function () {
    fillExecutive(Livewire::actingAs($this->admin)->test(Index::class))->call('save')->assertHasNoErrors();

    $raw = DB::table('executives')->value('cpf_encrypted');
    expect($raw)->not->toContain('52998224725');

    $log = DB::table('activity_logs')->where('log_name', 'executivos')->value('properties');
    expect($log)->not->toContain('52998224725')->not->toContain('cpf');
});

it('recusa CPF inválido e CPF já usado por outro executivo', function () {
    fillExecutive(Livewire::actingAs($this->admin)->test(Index::class), ['cpf' => '111.111.111-11'])
        ->call('save')->assertHasErrors('form.cpf');

    fillExecutive(Livewire::actingAs($this->admin)->test(Index::class))->call('save')->assertHasNoErrors();

    fillExecutive(Livewire::actingAs($this->admin)->test(Index::class), ['email' => 'outro@ricarteprev.test', 'cpf' => '52998224725'])
        ->call('save')->assertHasErrors('form.cpf');

    expect(Executive::count())->toBe(1);
});

it('desligar o executivo tira o acesso e reativar devolve', function () {
    fillExecutive(Livewire::actingAs($this->admin)->test(Index::class))->call('save');
    $executive = Executive::firstOrFail();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $executive->id)->set('form.status', 'terminated')->call('save')->assertHasNoErrors();
    expect($executive->user->fresh()->active)->toBeFalse();

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('edit', $executive->id)->set('form.status', 'active')->call('save');
    expect($executive->user->fresh()->active)->toBeTrue();
});

it('salva a foto no disco privado e só a entrega a quem pode ver', function () {
    Storage::fake('local');

    fillExecutive(Livewire::actingAs($this->admin)->test(Index::class))
        ->set('photo', UploadedFile::fake()->image('foto.jpg', 300, 300))
        ->call('save')->assertHasNoErrors();

    $executive = Executive::firstOrFail();
    Storage::disk('local')->assertExists($executive->photo_path);
    expect($executive->photo_path)->toStartWith('photos/executives/');

    $this->actingAs($this->admin)->get(route('photos.executive', $executive))->assertOk();
    $this->actingAs($executive->user)->get(route('photos.executive', $executive))->assertOk();

    $otherExec = userWithRole(Role::Executive);
    $this->actingAs($otherExec)->get(route('photos.executive', $executive))->assertForbidden();
});

it('usa a meta do mês quando existe e a meta padrão nos outros meses', function () {
    $executive = Executive::factory()->create(['monthly_visits_target' => 60, 'monthly_contracts_target' => 12]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('openGoals', $executive->id)
        ->set('goalForm.month', '2026-12')
        ->set('goalForm.visits_target', 40)
        ->set('goalForm.contracts_target', 8)
        ->call('saveGoal')
        ->assertHasNoErrors();

    expect($executive->targetsFor(CarbonImmutable::parse('2026-12-15')))
        ->toBe(['visits' => 40, 'contracts' => 8, 'source' => 'month'])
        ->and($executive->targetsFor(CarbonImmutable::parse('2026-11-01')))
        ->toBe(['visits' => 60, 'contracts' => 12, 'source' => 'default']);
});

it('não duplica a meta de um mesmo mês', function () {
    $executive = Executive::factory()->create();
    $component = Livewire::actingAs($this->admin)->test(Index::class)->call('openGoals', $executive->id);

    $component->set('goalForm.month', '2026-12')->set('goalForm.visits_target', 40)->set('goalForm.contracts_target', 8)->call('saveGoal');
    $component->set('goalForm.month', '2026-12')->set('goalForm.visits_target', 50)->set('goalForm.contracts_target', 9)->call('saveGoal');

    expect(Goal::count())->toBe(1)
        ->and(Goal::first()->visits_target)->toBe(50);
});

it('deixa o gestor ver a lista mas não editar', function () {
    $manager = userWithRole(Role::Manager);
    $executive = Executive::factory()->create();

    Livewire::actingAs($manager)->test(Index::class)
        ->assertSee($executive->user->name)
        ->assertDontSee('Novo executivo')
        ->call('edit', $executive->id)
        ->assertForbidden();
});

it('lista só cidades atendidas no formulário', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Juazeiro do Norte/CE')
        ->assertDontSee('Fortaleza/CE');
});

it('não mostra executivos para quem não tem permissão', function () {
    $indicator = userWithRole(Role::Indicator);

    Livewire::actingAs($indicator)->test(Index::class)->assertForbidden();
    expect(User::count())->toBeGreaterThan(0);
});
