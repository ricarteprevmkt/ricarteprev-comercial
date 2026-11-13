<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Usuários de teste, um por perfil, para desenvolvimento local.
 * Recusa rodar fora do ambiente "local".
 *
 *   php artisan db:seed --class=LocalDevSeeder
 *
 * Senha de todos: "password". E-mails: <perfil>@ricarteprev.test
 */
class LocalDevSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('LocalDevSeeder só pode ser executado com APP_ENV=local.');
        }

        foreach (Role::cases() as $role) {
            $user = User::query()->firstOrCreate(
                ['email' => "{$role->value}@ricarteprev.test"],
                ['name' => "Teste {$role->label()}", 'password' => 'password', 'active' => true],
            );
            $user->syncRoles([$role->value]);
        }
    }
}
