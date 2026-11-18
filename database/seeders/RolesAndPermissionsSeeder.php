<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Support\Acl;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotente: cria permissões e perfis que faltarem. Os padrões de cada perfil
 * só são aplicados quando o perfil é criado agora, para não desfazer ajustes
 * feitos depois pelo administrador. Permissão nova (de um módulo novo) é dada
 * aos perfis existentes que a têm como padrão.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $new = [];
        foreach (Acl::permissionNames() as $name) {
            $existed = Permission::query()->where(['name' => $name, 'guard_name' => 'web'])->exists();
            Permission::findOrCreate($name, 'web');
            if (! $existed) {
                $new[] = $name;
            }
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
            $defaults = Acl::defaults()[$roleEnum->value] ?? [];

            if ($roleEnum === RoleEnum::Admin) {
                continue;
            }

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($defaults);
            } elseif ($added = array_values(array_intersect($new, $defaults))) {
                $role->givePermissionTo($added);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
