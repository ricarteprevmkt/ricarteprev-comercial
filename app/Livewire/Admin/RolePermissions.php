<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Support\Acl;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matriz de permissões por perfil. O administrador não aparece: tem acesso total.
 */
#[Title('Perfis e permissões')]
class RolePermissions extends Component
{
    /** @var array<string, array<int, string>> perfil => permissões marcadas */
    public array $matrix = [];

    public function mount(): void
    {
        $this->authorize('roles.manage');
        $this->load();
    }

    private function load(): void
    {
        $this->matrix = [];

        foreach ($this->editableRoles() as $role) {
            $this->matrix[$role->value] = $this->roleModel($role)->permissions
                ->map(fn ($permission): string => $permission->name)
                ->values()
                ->all();
        }
    }

    private function roleModel(Role $role): RoleModel
    {
        return RoleModel::query()->where('name', $role->value)->where('guard_name', 'web')->firstOrFail();
    }

    /** @return list<Role> */
    private function editableRoles(): array
    {
        return array_values(array_filter(Role::cases(), fn (Role $r) => $r !== Role::Admin));
    }

    public function save(): void
    {
        $this->authorize('roles.manage');
        $valid = Acl::permissionNames();

        DB::transaction(function () use ($valid) {
            foreach ($this->editableRoles() as $role) {
                $model = $this->roleModel($role);
                $before = $model->permissions->map(fn ($permission): string => $permission->name)->sort()->values()->all();
                $after = collect($this->matrix[$role->value] ?? [])->intersect($valid)->unique()->sort()->values()->all();

                if ($before === $after) {
                    continue;
                }

                $model->syncPermissions($after);

                activity('perfis')->performedOn($model)->event('permissions_changed')
                    ->withProperties([
                        'adicionadas' => array_values(array_diff($after, $before)),
                        'removidas' => array_values(array_diff($before, $after)),
                    ])
                    ->log("Permissões do perfil {$role->label()} alteradas");
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->load();
        Flux::toast(variant: 'success', text: 'Permissões salvas. Valem a partir da próxima tela que cada usuário abrir.');
    }

    public function restoreDefaults(string $role): void
    {
        $this->authorize('roles.manage');
        $this->matrix[$role] = Acl::defaults()[$role] ?? [];
    }

    public function render(): View
    {
        return view('livewire.admin.role-permissions', [
            'roles' => $this->editableRoles(),
            'groups' => Acl::permissions(),
        ]);
    }
}
