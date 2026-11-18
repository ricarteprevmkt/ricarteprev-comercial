@php
    use App\Support\Phone;
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
    $stats = $this->stats;
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Interessados" subtitle="Contatos que chegaram pelo WhatsApp e ainda não entraram no funil. Qualifique para virar contato e oportunidade." icon="chat-bubble-left-right">
        @if ($canManage)
            <x-slot:actions>
                <flux:modal.trigger name="import-prospects">
                    <flux:button variant="primary" icon="arrow-up-tray">Importar planilha do C2</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <x-stat label="Aguardando" :value="number_format($stats['new'], 0, ',', '.')" hint="interessados novos" icon="inbox" />
        <x-stat label="Sem executivo" :value="number_format($stats['unassigned'], 0, ',', '.')" hint="para distribuir" icon="user-minus" :tone="$stats['unassigned'] > 0 ? 'warning' : null" />
        <x-stat label="Chegaram na semana" :value="$stats['week']" hint="últimos 7 dias" icon="calendar-days" />
        <x-stat label="Qualificados" :value="$stats['qualified']" :hint="$stats['discarded'].' descartado(s)'" icon="check-badge" />
        <x-stat label="Taxa de qualificação" :value="$pct($stats['rate'])" hint="qualificados ÷ (qualificados + descartados)" icon="receipt-percent" />
    </div>

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Nome ou telefone" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="status" class="max-w-44" aria-label="Situação">
                <flux:select.option value="todos">Todas as situações</flux:select.option>
                @foreach ($statuses as $s)
                    <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($executives->isNotEmpty())
                <flux:select wire:model.live="executive" class="max-w-56" aria-label="Executivo">
                    <flux:select.option value="">Todos os executivos</flux:select.option>
                    <flux:select.option value="nenhum">Sem executivo</flux:select.option>
                    @foreach ($executives as $e)
                        <flux:select.option :value="$e->id">{{ $e->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:select wire:model.live="period" class="max-w-44" aria-label="Entrada">
                @foreach (\App\Livewire\Prospects\Index::PERIODS as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:checkbox wire:model.live="withDemand" label="Com demanda descrita" class="self-center" />
        </div>

        @if ($canManage && $selected !== [])
            <div class="flex flex-wrap items-center gap-3 border-b border-zinc-100 bg-brand-50 px-4 py-3">
                <span class="text-sm font-medium text-brand-900">{{ count($selected) }} selecionado(s)</span>
                <flux:select wire:model="assignTo" size="sm" class="max-w-56" aria-label="Atribuir a">
                    <flux:select.option value="">Atribuir a…</flux:select.option>
                    @foreach ($executives as $e)
                        <flux:select.option :value="$e->id">{{ $e->user->name }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="ninguem">Tirar o executivo</flux:select.option>
                </flux:select>
                <flux:button size="sm" variant="primary" wire:click="assign">Aplicar</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="$set('selected', [])">Limpar seleção</flux:button>
                @error('assignTo')<span class="text-sm text-red-600">{{ $message }}</span>@enderror
            </div>
        @endif

        @if ($this->prospects->isEmpty())
            <x-empty-state icon="chat-bubble-left-right" title="Nenhum interessado aqui"
                :description="$canManage ? 'Exporte os leads no CRM do C2 e use “Importar planilha do C2”.' : 'Os interessados atribuídos a você aparecem aqui.'" />
        @else
            <flux:table :paginate="$this->prospects" class="px-4">
                <flux:table.columns>
                    @if ($canManage)<flux:table.column class="w-8"><span class="sr-only">Selecionar</span></flux:table.column>@endif
                    <flux:table.column>Interessado</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Demanda</flux:table.column>
                    <flux:table.column class="max-sm:hidden">Entrada</flux:table.column>
                    <flux:table.column class="max-md:hidden">Executivo</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->prospects as $p)
                        <flux:table.row :key="$p->id">
                            @if ($canManage)
                                <flux:table.cell>
                                    @if ($p->status === \App\Enums\ProspectStatus::New)
                                        <flux:checkbox wire:model.live="selected" :value="$p->id" aria-label="Selecionar {{ $p->displayName() }}" />
                                    @endif
                                </flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $p->displayName() }}</div>
                                <div class="flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                                    <a href="{{ Phone::whatsappUrl($p->phone) }}" target="_blank" rel="noopener" class="tabular-nums text-success-500 hover:underline">{{ Phone::format($p->phone) }}</a>
                                    @if ($p->status !== \App\Enums\ProspectStatus::New)
                                        <flux:badge size="sm" :color="$p->status->color()">{{ $p->status->label() }}{{ $p->discard_reason ? ': '.(\App\Enums\ProspectStatus::discardReasons()[$p->discard_reason] ?? '') : '' }}</flux:badge>
                                    @elseif ($p->lead)
                                        <flux:badge size="sm" color="amber">já é contato: {{ $p->lead->name }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden max-w-96">
                                <span class="line-clamp-2 text-sm text-zinc-600" title="{{ $p->demand }}">{{ $p->demand ?? '—' }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="max-sm:hidden tabular-nums">{{ $p->entered_at->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell class="max-md:hidden">{{ $p->executive?->user->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    @can('qualify', $p)
                                        <flux:button size="sm" variant="primary" wire:click="$dispatch('lead-form:from-prospect', { id: {{ $p->id }} })">Qualificar</flux:button>
                                    @endcan
                                    @can('discard', $p)
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="confirmDiscard({{ $p->id }})" aria-label="Descartar {{ $p->displayName() }}" />
                                    @endcan
                                    @if ($p->status === \App\Enums\ProspectStatus::Qualified && $p->lead)
                                        <flux:button size="sm" variant="ghost" :href="route('funnel.leads', ['busca' => $p->lead->name])" wire:navigate>Ver contato</flux:button>
                                    @endif
                                    @if ($p->status === \App\Enums\ProspectStatus::Discarded && $canManage)
                                        <flux:button size="sm" variant="ghost" wire:click="reopen({{ $p->id }})">Reabrir</flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <flux:modal name="import-prospects" class="w-full max-w-lg">
        <form wire:submit="import" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Importar planilha do C2</flux:heading>
                <flux:text class="mt-1">No C2: CRM → exportar leads (Excel ou CSV). Quem já foi importado é só atualizado, pelo telefone: nada se duplica, e a situação e o executivo de cada interessado não mudam.</flux:text>
            </div>
            <flux:input type="file" wire:model="file" accept=".xlsx,.csv" label="Planilha" />
            <div wire:loading wire:target="file" class="text-sm text-zinc-500">Enviando o arquivo…</div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="import,file">Importar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="discard-prospect" class="w-full max-w-sm">
        <form wire:submit="discard" class="flex flex-col gap-4">
            <flux:heading size="lg">Descartar interessado</flux:heading>
            <flux:select wire:model="discardReason" label="Motivo">
                <flux:select.option value="">Escolha…</flux:select.option>
                @foreach ($reasons as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Descartar</flux:button>
            </div>
        </form>
    </flux:modal>

    <livewire:funnel.lead-form />
</div>
