<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-800 antialiased">
        {{-- A classe "dark" fica só na sidebar: os componentes Flux dentro dela usam texto claro sobre o azul-marinho. --}}
        <flux:sidebar sticky collapsible="mobile" class="dark border-e border-brand-950 bg-brand-900!">
            <flux:sidebar.header>
                <a href="{{ route('start') }}" class="block px-2 pt-2" wire:navigate>
                    <img src="{{ asset('images/brand/logo-horizontal-branco.png') }}" alt="Ricarte Prev" class="w-44">
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="mt-4">
                @foreach (\App\Support\Navigation::for(auth()->user()) as $group)
                    <flux:sidebar.group :heading="$loop->first ? null : $group['group']" class="grid">
                        @foreach ($group['items'] as $item)
                            <flux:sidebar.item
                                :icon="$item['icon']"
                                :href="route($item['route'])"
                                :current="request()->routeIs(...(array) ($item['active'] ?? $item['route']))"
                                wire:navigate
                            >
                                {{ $item['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="border-t border-white/10 pt-2">
                <x-desktop-user-menu />
            </div>

            <div class="flex flex-col items-center gap-1.5 border-t border-white/10 px-3 pt-3 text-center text-[11px] text-brand-100/60">
                <span>Desenvolvido por</span>
                <img src="{{ asset('images/dbase.png') }}" alt="dbase" width="197" height="200" class="h-10 w-auto">
                <span class="tabular-nums">v{{ config('app.version') }}</span>
            </div>
        </flux:sidebar>

        {{-- Cabeçalho do celular --}}
        <flux:header class="border-b border-zinc-200 bg-white lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="Abrir menu" />
            <img src="{{ asset('images/brand/logo-horizontal.png') }}" alt="Ricarte Prev" class="ms-2 h-8">
            <flux:spacer />
            <livewire:notification-bell />
            <x-user-menu />
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
