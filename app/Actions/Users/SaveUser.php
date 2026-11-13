<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou atualiza um usuário com um único perfil.
 *
 * Senha: se não for informada na criação, o usuário recebe por e-mail um link
 * para criar a própria senha (a senha provisória aleatória nunca é exibida).
 */
final class SaveUser
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, active?: bool, password?: string|null}  $data
     * @return array{user: User, reset_link_sent: bool}
     */
    public function handle(?User $user, array $data, Role $role, ?User $actor = null): array
    {
        $creating = $user === null;
        $user ??= new User;

        if ($actor !== null && ! $creating) {
            $this->guardSelfLockout($user, $actor, $role, (bool) ($data['active'] ?? true));
        }

        $password = $data['password'] ?? null;

        DB::transaction(function () use ($user, $data, $role, $creating, $password) {
            $user->fill([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'active' => $data['active'] ?? true,
            ]);

            if ($password) {
                $user->password = $password;
            } elseif ($creating) {
                $user->password = Str::password(32);
            }

            if ($creating) {
                // Contas criadas pelo administrador já nascem com e-mail confirmado.
                $user->email_verified_at = now();
            }

            $user->save();

            if (! $user->hasRole($role->value) || $user->roles()->count() !== 1) {
                $previous = $user->getRoleNames()->implode(', ');
                $user->syncRoles([$role->value]);

                activity('usuarios')->performedOn($user)->event('role_changed')
                    ->withProperties(['old' => $previous, 'new' => $role->value])
                    ->log("Perfil alterado para {$role->label()}");
            }
        });

        $resetSent = false;

        if ($creating && ! $password) {
            $resetSent = Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
        }

        return ['user' => $user, 'reset_link_sent' => $resetSent];
    }

    /**
     * Impede que o administrador tire o próprio acesso ou deixe o sistema sem administrador ativo.
     */
    private function guardSelfLockout(User $user, User $actor, Role $role, bool $active): void
    {
        if ($user->is($actor) && ! $active) {
            throw ValidationException::withMessages(['form.active' => 'Você não pode desativar o seu próprio acesso.']);
        }

        if ($user->is($actor) && $user->isAdmin() && $role !== Role::Admin) {
            throw ValidationException::withMessages(['form.role' => 'Você não pode remover o seu próprio perfil de administrador.']);
        }

        if ($user->isAdmin() && ($role !== Role::Admin || ! $active) && $this->otherActiveAdmins($user) === 0) {
            throw ValidationException::withMessages(['form.role' => 'O sistema precisa de pelo menos um administrador ativo.']);
        }
    }

    private function otherActiveAdmins(User $user): int
    {
        return User::role(Role::Admin->value)->active()->whereKeyNot($user->getKey())->count();
    }
}
