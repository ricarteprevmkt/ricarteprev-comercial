<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureAuthAudit();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        CarbonImmutable::setLocale('pt_BR');

        // Em desenvolvimento, acusa N+1 e atributos inexistentes cedo.
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        // Administrador tem acesso total, independentemente das permissões do perfil.
        Gate::before(fn (User $user) => $user->hasRole(Role::Admin->value) ? true : null);
    }

    protected function configureAuthAudit(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            activity('acesso')->causedBy($event->user)->event('login')
                ->withProperties(['ip' => request()->ip()])->log('Entrou no sistema');
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                activity('acesso')->causedBy($event->user)->event('logout')->log('Saiu do sistema');
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            activity('acesso')->event('login_failed')
                ->withProperties(['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()])
                ->log('Tentativa de login sem sucesso');
        });
    }
}
