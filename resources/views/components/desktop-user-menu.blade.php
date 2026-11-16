{{-- Menu do usuário no rodapé da barra lateral (abre para cima). --}}
@php($user = auth()->user())

<flux:dropdown position="top" align="start" class="w-full">
    <button type="button" class="flex w-full items-center gap-3 rounded-lg p-2 text-start transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-brand-300" data-test="sidebar-menu-button">
        <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" class="shrink-0 bg-brand-100! text-brand-900!" />
        <span class="min-w-0 flex-1 leading-tight">
            <span class="block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
            <span class="block truncate text-xs text-brand-100/70">{{ $user->primaryRole()?->label() ?? 'Sem perfil' }}</span>
        </span>
        <flux:icon.chevrons-up-down variant="micro" class="shrink-0 text-brand-100/60" />
    </button>

    <flux:menu class="min-w-60">
        <div class="px-2 py-1.5 text-sm leading-tight">
            <div class="truncate font-semibold">{{ $user->name }}</div>
            <div class="truncate opacity-70">{{ $user->email }}</div>
        </div>
        <flux:menu.separator />
        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>Minha conta</flux:menu.item>
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                Sair
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
