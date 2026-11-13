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
 * feitos depois pelo administrador.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Acl::permissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);

            if ($role->wasRecentlyCreated && $roleEnum !== RoleEnum::Admin) {
                $role->syncPermissions(Acl::defaults()[$roleEnum->value] ?? []);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
