<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased">
        <div class="grid min-h-svh lg:grid-cols-[1fr_1.1fr]">
            <aside class="relative hidden overflow-hidden bg-brand-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <img src="{{ asset('images/brand/logo-horizontal-branco.png') }}" alt="Ricarte Prev" class="w-56">
                <div class="max-w-md">
                    <p class="text-3xl font-semibold leading-tight">Gestão comercial da equipe em campo.</p>
                    <p class="mt-4 text-brand-100/80">Visitas, funil, custos e indicações regionais em um só lugar.</p>
                </div>
                <p class="text-sm text-brand-100/60">Mais que benefícios, segurança para o seu futuro.</p>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -right-40 size-[28rem] rounded-full border border-white/10"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-24 size-[20rem] rounded-full border border-gold-400/30"></div>
            </aside>

            <main class="flex flex-col items-center justify-center gap-8 p-6 md:p-10">
                <img src="{{ asset('images/brand/logo-horizontal.png') }}" alt="Ricarte Prev" class="w-48 lg:hidden">
                <div class="flex w-full max-w-sm flex-col gap-6">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
