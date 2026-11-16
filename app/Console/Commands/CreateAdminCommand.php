<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name= : Nome completo} {--email= : E-mail de acesso} {--password-stdin : Lê a senha da entrada padrão (para servidores sem proc_open)}')]
#[Description('Cria um usuário administrador (a senha é pedida de forma oculta)')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        // Os prompts interativos usam proc_open, bloqueado na Hostinger. Lá, a senha
        // chega pela entrada padrão (lida pelo "read -s" do bash, que não a mostra).
        $secret = $this->option('password-stdin') ? rtrim((string) stream_get_contents(STDIN), "\r\n") : null;

        $name = $this->option('name') ?: text('Nome completo', required: true);
        $email = $this->option('email') ?: text('E-mail', required: true);
        $secret ??= password('Senha', required: true, hint: app()->isProduction() ? 'Mínimo de 12 caracteres, com letras, números e símbolos.' : '');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $secret) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $secret,
                'active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(Role::Admin->value);
        });

        $this->info("Administrador {$email} criado.");

        return self::SUCCESS;
    }
}
