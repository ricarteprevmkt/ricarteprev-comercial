<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Testes de Feature usam o banco MySQL "ricarteprev_testing" (veja phpunit.xml),
| para que agregações e restrições se comportem como em produção.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Cria um usuário ativo com o perfil informado (os perfis são semeados na hora).
 */
function userWithRole(Role $role, array $attributes = []): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);

    return $user;
}
