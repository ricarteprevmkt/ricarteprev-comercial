<div class="flex flex-col gap-6">
    <x-page-header title="Auditoria" subtitle="Quem fez o quê e quando. Os registros são guardados por 2 anos." />

    <x-card :padding="false">
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-100 p-4">
            <flux:select wire:model.live="logName" class="max-w-52" aria-label="Área">
                <flux:select.option value="">Todas as áreas</flux:select.option>
                @foreach ($areas as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="causer" class="max-w-56" aria-label="Usuário">
                <flux:select.option value="">Todos os usuários</flux:select.option>
                @foreach ($users as $user)
                    <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="from" label="De" class="max-w-44" />
            <flux:input type="date" wire:model.live="to" label="Até" class="max-w-44" />
        </div>

        @if ($this->activities->isEmpty())
            <x-empty-state icon="clipboard-document-list" title="Nenhum registro no filtro selecionado" />
        @else
            <ul class="divide-y divide-zinc-100">
                @foreach ($this->activities as $activity)
                    @php($changes = \App\Livewire\Admin\Audit::changes($activity))
                    <li wire:key="act-{{ $activity->id }}">
                        <button type="button" wire:click="toggle({{ $activity->id }})" class="flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-start hover:bg-zinc-50" aria-expanded="{{ $expanded === $activity->id ? 'true' : 'false' }}">
                            <span class="w-32 shrink-0 text-xs tabular-nums text-zinc-500">{{ $activity->created_at->format('d/m/Y H:i:s') }}</span>
                            <span class="w-40 shrink-0 truncate text-sm font-medium text-zinc-900">{{ $activity->causer?->name ?? 'Sistema / visitante' }}</span>
                            <flux:badge size="sm" color="zinc">{{ $areas[$activity->log_name] ?? $activity->log_name }}</flux:badge>
                            <span class="min-w-0 flex-1 truncate text-sm text-zinc-700">
                                <span class="font-medium">{{ $events[$activity->event] ?? $activity->event }}</span>
                                · {{ $activity->description }}
                                @if ($activity->subject_type)
                                    <span class="text-zinc-400">({{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }})</span>
                                @endif
                            </span>
                            @if ($changes->isNotEmpty())
                                <flux:icon :name="$expanded === $activity->id ? 'chevron-up' : 'chevron-down'" variant="micro" class="text-zinc-400" />
                            @endif
                        </button>
                        @if ($expanded === $activity->id && $changes->isNotEmpty())
                            <div class="overflow-x-auto bg-zinc-50 px-5 pb-4">
                                <table class="min-w-full text-sm">
                                    <thead><tr class="text-left text-xs text-zinc-500"><th class="py-2 pe-4 font-medium">Campo</th><th class="py-2 pe-4 font-medium">Antes</th><th class="py-2 font-medium">Depois</th></tr></thead>
                                    <tbody>
                                        @foreach ($changes as $change)
                                            <tr class="border-t border-zinc-200">
                                                <td class="py-1.5 pe-4 font-mono text-xs text-zinc-600">{{ $change['field'] }}</td>
                                                <td class="py-1.5 pe-4 text-red-700">{{ $change['old'] }}</td>
                                                <td class="py-1.5 text-success-500">{{ $change['new'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-zinc-100 px-4 py-3">{{ $this->activities->links() }}</div>
        @endif
    </x-card>
</div>
