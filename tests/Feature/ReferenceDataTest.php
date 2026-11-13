<?php

use App\Enums\Role;
use App\Models\City;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Spatie\Permission\Models\Role as RoleModel;

it('importa todos os municípios do IBGE com coordenadas', function () {
    $this->seed(CitySeeder::class);

    expect(City::count())->toBeGreaterThan(5500)
        ->and(City::where('uf', 'CE')->count())->toBe(184);

    $juazeiro = City::where('ibge_code', 2307304)->firstOrFail();
    expect($juazeiro->name)->toBe('Juazeiro do Norte')
        ->and((float) $juazeiro->latitude)->toBeLessThan(-7.0);
});

it('reimporta municípios sem perder região nem cidade atendida', function () {
    $this->seed(CitySeeder::class);
    $region = Region::create(['name' => 'Cariri']);
    City::where('ibge_code', 2307304)->firstOrFail()->update(['region_id' => $region->id, 'served' => true]);

    $this->seed(CitySeeder::class);

    $city = City::where('ibge_code', 2307304)->firstOrFail();
    expect($city->served)->toBeTrue()
        ->and($city->region_id)->toBe($region->id)
        ->and(City::where('ibge_code', 2307304)->count())->toBe(1);
});

it('não desfaz ajustes de permissão ao rodar o seeder de novo', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    RoleModel::findByName(Role::Executive->value)->revokePermissionTo('expenses.manage');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::findByName(Role::Executive->value)->hasPermissionTo('expenses.manage'))->toBeFalse();
});

it('cria os quatro perfis do sistema', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toBe(['administrador', 'executivo', 'gestor', 'indicador']);
});

it('lê parâmetros com tipo correto e atualiza o cache ao salvar', function () {
    $this->seed(SystemSettingsSeeder::class);
    $admin = User::factory()->create();

    expect(SystemSetting::get('funnel.stale_after_days'))->toBe(7)
        ->and(SystemSetting::get('routes.road_factor'))->toBe('1.30')
        ->and(SystemSetting::get('routes.cost_per_km'))->toBeNull()
        ->and(SystemSetting::get('routes.cost_per_km', '0'))->toBe('0');

    SystemSetting::put('funnel.stale_after_days', 10, $admin->id);

    expect(SystemSetting::get('funnel.stale_after_days'))->toBe(10);
    $this->assertDatabaseHas('activity_logs', ['log_name' => 'parametros', 'subject_type' => SystemSetting::class]);
});

it('cria administrador pelo comando, com senha oculta', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->artisan('app:create-admin', ['--name' => 'Sérgio Ricarte', '--email' => 'admin@ricarteprev.test'])
        ->expectsQuestion('Senha', 'senha-local-de-teste')
        ->assertSuccessful();

    $user = User::where('email', 'admin@ricarteprev.test')->firstOrFail();
    expect($user->isAdmin())->toBeTrue()
        ->and($user->active)->toBeTrue();
});

it('recusa criar administrador com e-mail repetido', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    User::factory()->create(['email' => 'admin@ricarteprev.test']);

    $this->artisan('app:create-admin', ['--name' => 'Outro', '--email' => 'admin@ricarteprev.test'])
        ->expectsQuestion('Senha', 'senha-local-de-teste')
        ->assertFailed();
});
