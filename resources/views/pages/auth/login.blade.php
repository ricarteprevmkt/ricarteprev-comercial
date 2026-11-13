<x-layouts::auth title="Entrar">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Acesse sua conta" description="Use o e-mail e a senha cadastrados pelo administrador." />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                label="E-mail"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="nome@ricarteprev.com.br"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    label="Senha"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        Esqueci a senha
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" label="Manter conectado neste aparelho" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                Entrar
            </flux:button>
        </form>

        <p class="text-center text-sm text-zinc-500">
            Não tem acesso? Fale com o administrador do sistema.
        </p>
    </div>
</x-layouts::auth>
