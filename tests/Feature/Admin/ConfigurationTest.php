<?php

use App\Enums\CatalogType;
use App\Enums\Role;
use App\Livewire\Admin\Audit;
use App\Livewire\Admin\Catalogs;
use App\Livewire\Admin\RegionsCities;
use App\Livewire\Admin\RolePermissions;
use App\Livewire\Admin\Settings;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Catalogs\LeadSource;
use App\Models\City;
use App\Models\Region;
use App\Models\SystemSetting;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\SystemSettingsSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    $this->admin = userWithRole(Role::Admin);
});

it('salva permissões por perfil, registra o que mudou e aplica na hora', function () {
    $indicator = userWithRole(Role::Indicator);
    $this->actingAs($indicator)->get(route('reports.index'))->assertForbidden();

    $component = Livewire::actingAs($this->admin)->test(RolePermissions::class);
    $matrix = $component->get('matrix');
    $matrix['indicador'][] = 'reports.view';
    $component->set('matrix', $matrix)->call('save')->assertHasNoErrors();

    expect(RoleModel::findByName('indicador')->hasPermissionTo('reports.view'))->toBeTrue();
    $this->assertDatabaseHas('activity_logs', ['log_name' => 'perfis', 'event' => 'permissions_changed']);
    $this->actingAs($indicator->fresh())->get(route('reports.index'))->assertOk();
});

it('ignora permissões inexistentes enviadas pelo navegador', function () {
    $component = Livewire::actingAs($this->admin)->test(RolePermissions::class);
    $matrix = $component->get('matrix');
    $matrix['executivo'][] = 'permissao.inventada';
    $component->set('matrix', $matrix)->call('save');

    expect(RoleModel::findByName('executivo')->permissions->pluck('name'))->not->toContain('permissao.inventada');
});

it('cria região e associa cidades em lote, marcando como atendidas', function () {
    $this->seed(CitySeeder::class);
    $ids = City::whereIn('ibge_code', [2307304, 2304202, 2301901])->pluck('id')->all();

    $component = Livewire::actingAs($this->admin)->test(RegionsCities::class)
        ->set('regionForm.name', 'Cariri')
        ->call('saveRegion')
        ->assertHasNoErrors();

    $region = Region::where('name', 'Cariri')->firstOrFail();

    $component->set('selected', $ids)->set('bulkRegion', (string) $region->id)->call('applyBulk', 'region');

    expect(City::whereKey($ids)->where('served', true)->where('region_id', $region->id)->count())->toBe(3);
});

it('alterna cidade atendida e não aceita região com nome repetido', function () {
    $this->seed(CitySeeder::class);
    $city = City::where('ibge_code', 2307304)->firstOrFail();
    Region::create(['name' => 'Cariri']);

    Livewire::actingAs($this->admin)->test(RegionsCities::class)
        ->call('toggleServed', $city->id)
        ->set('regionForm.name', 'Cariri')
        ->call('saveRegion')
        ->assertHasErrors('regionForm.name');

    expect($city->fresh()->served)->toBeTrue();
});

it('semeia os catálogos padrão uma única vez', function () {
    $this->seed(CatalogSeeder::class);
    LeadSource::where('name', 'Site')->update(['active' => false]);
    $this->seed(CatalogSeeder::class);

    expect(LeadSource::count())->toBe(7)
        ->and(LeadSource::where('name', 'Site')->value('active'))->toBeFalse()
        ->and(ExpenseCategory::where('name', 'Equipe comercial')->value('nature'))->toBe('fixed');

    foreach (CatalogType::cases() as $type) {
        expect($type->model()::count())->toBeGreaterThan(0);
    }
});

it('adiciona item a um catálogo, gera slug e desativa sem excluir', function () {
    $this->seed(CatalogSeeder::class);

    $component = Livewire::actingAs($this->admin)->test(Catalogs::class)
        ->set('type', 'categorias-de-despesa')
        ->call('create')
        ->set('form.name', 'Manutenção de veículo')
        ->set('form.nature', 'variable')
        ->call('save')
        ->assertHasNoErrors();

    $item = ExpenseCategory::where('name', 'Manutenção de veículo')->firstOrFail();
    expect($item->slug)->toBe('manutencao-de-veiculo');

    $component->call('toggle', $item->id);
    expect($item->fresh()->active)->toBeFalse()
        ->and(ExpenseCategory::whereKey($item->id)->exists())->toBeTrue();
});

it('não aceita item repetido no mesmo catálogo', function () {
    $this->seed(CatalogSeeder::class);

    Livewire::actingAs($this->admin)->test(Catalogs::class)
        ->set('type', 'origens')->call('create')->set('form.name', 'Instagram')->call('save')
        ->assertHasErrors('form.name');
});

it('valida e salva os parâmetros comerciais', function () {
    $this->seed(SystemSettingsSeeder::class);

    Livewire::actingAs($this->admin)->test(Settings::class)
        ->set('values.routes__road_factor', '5')
        ->call('save')
        ->assertHasErrors('values.routes__road_factor');

    Livewire::actingAs($this->admin)->test(Settings::class)
        ->set('values.routes__road_factor', '1.25')
        ->set('values.routes__cost_per_km', '0.85')
        ->set('values.funnel__stale_after_days', '10')
        ->call('save')
        ->assertHasNoErrors();

    expect(SystemSetting::get('routes.road_factor'))->toBe('1.25')
        ->and(SystemSetting::get('routes.cost_per_km'))->toBe('0.85')
        ->and(SystemSetting::get('funnel.stale_after_days'))->toBe(10)
        ->and(SystemSetting::query()->where('key', 'funnel.stale_after_days')->value('updated_by'))->toBe($this->admin->id);
});

it('mostra a auditoria com filtro por área e o detalhe das mudanças', function () {
    $region = Region::create(['name' => 'Cariri']);
    $region->update(['name' => 'Cariri Cearense']);

    Livewire::actingAs($this->admin)->test(Audit::class)
        ->set('logName', 'regioes')
        ->assertSee('Regiões')
        ->call('toggle', Activity::where('log_name', 'regioes')->latest('id')->value('id'))
        ->assertSee('Cariri Cearense');
});

it('nega as telas de configuração ao gestor', function () {
    $manager = userWithRole(Role::Manager);

    foreach ([RolePermissions::class, RegionsCities::class, Catalogs::class, Settings::class, Audit::class] as $component) {
        Livewire::actingAs($manager)->test($component)->assertForbidden();
    }
});
