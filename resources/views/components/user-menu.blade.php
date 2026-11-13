@props(['showName' => false])

@php($user = auth()->user())

<flux:dropdown position="bottom" align="end">
    <button type="button" class="flex items-center gap-3 rounded-lg p-1 text-start hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-brand-500" data-test="user-menu-button">
        <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" class="bg-brand-100! text-brand-900!" />
        @if ($showName)
            <span class="hidden leading-tight md:block">
                <span class="block text-sm font-semibold text-zinc-900">{{ $user->name }}</span>
                <span class="block text-xs text-zinc-500">{{ $user->primaryRole()?->label() ?? 'Sem perfil' }}</span>
            </span>
            <flux:icon.chevron-down variant="micro" class="hidden text-zinc-400 md:block" />
        @endif
    </button>

    <flux:menu>
        <div class="px-2 py-1.5 text-sm leading-tight">
            <div class="font-semibold text-zinc-900">{{ $user->name }}</div>
            <div class="text-zinc-500">{{ $user->email }}</div>
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
