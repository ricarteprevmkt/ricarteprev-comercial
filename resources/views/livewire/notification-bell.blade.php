<div wire:poll.120s x-data="{ open: false }" x-on:keydown.escape.window="open = false" class="relative">
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        class="relative rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 hover:text-brand-900 focus-visible:outline-2 focus-visible:outline-brand-500"
        aria-label="Notificações{{ $unread ? " ({$unread} não lidas)" : '' }}"
    >
        <flux:icon.bell class="size-5" />
        @if ($unread > 0)
            <span class="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-gold-400 px-1 text-[10px] font-bold leading-4 text-brand-900">
                {{ $unread > 9 ? '9+' : $unread }}
            </span>
        @endif
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        x-on:click.outside="open = false"
        class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg"
    >
        <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3">
            <span class="text-sm font-semibold text-brand-900">Notificações</span>
            @if ($unread > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-brand-500 hover:underline">Marcar todas como lidas</button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($items as $item)
                <button type="button" wire:click="open('{{ $item->id }}')" class="flex w-full gap-3 border-b border-zinc-100 px-4 py-3 text-start last:border-0 hover:bg-zinc-50 {{ $item->read_at ? '' : 'bg-brand-50/60' }}">
                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $item->read_at ? 'bg-transparent' : 'bg-gold-400' }}"></span>
                    <span class="min-w-0">
                        <span class="block text-sm text-zinc-800">{{ $item->data['message'] ?? 'Notificação' }}</span>
                        <span class="block text-xs text-zinc-500">{{ $item->created_at->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-zinc-500">Nenhuma notificação por enquanto.</p>
            @endforelse
        </div>
    </div>
</div>
