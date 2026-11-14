<div class="flex flex-col gap-6">
    <x-page-header title="Funil comercial" subtitle="Oportunidades, etapas, próximas ações e prazos.">
        <x-slot:actions>
            @can('leads.manage')
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('lead-form:create')">Nova oportunidade</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-funnel-tabs />

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar pelo nome do cliente" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="stageFilter" class="max-w-56" aria-label="Etapa">
                <flux:select.option value="abertas">Em aberto</flux:select.option>
                <flux:select.option value="atrasadas">Com prazo vencido</flux:select.option>
                <flux:select.option value="paradas">Sem atualização há mais de {{ $staleDays }} dias</flux:select.option>
                <flux:select.option value="todas">Todas</flux:select.option>
                @foreach ($stages as $stage)
                    <flux:select.option :value="$stage->value">Etapa: {{ $stage->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="sourceFilter" class="max-w-44" aria-label="Origem">
                <flux:select.option value="">Todas as origens</flux:select.option>
                @foreach ($sources as $source)
                    <flux:select.option :value="$source->id">{{ $source->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($executives->isNotEmpty())
                <flux:select wire:model.live="executiveFilter" class="max-w-52" aria-label="Executivo">
                    <flux:select.option value="">Todos os executivos</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        @if ($this->opportunities->isEmpty())
            <x-empty-state icon="funnel" title="Nenhuma oportunidade neste filtro" description="Cadastre um contato e abra uma oportunidade para começar." />
        @else
            <flux:table :paginate="$this->opportunities" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Cliente</flux:table.column>
                    <flux:table.column>Etapa</flux:table.column>
                    <flux:table.column class="max-md:hidden">Próxima ação</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Responsável</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Última atualização</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->opportunities as $opportunity)
                        <flux:table.row :key="$opportunity->id">
                            <flux:table.cell>
                                <a href="{{ route('funnel.opportunities.show', $opportunity) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $opportunity->lead->name }}</a>
                                <div class="text-xs text-zinc-500">{{ $opportunity->lead->city?->label() }}{{ $opportunity->source ? ' · '.$opportunity->source->name : '' }}</div>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$opportunity->stage->color()">{{ $opportunity->stage->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-md:hidden">
                                @if ($opportunity->next_action || $opportunity->next_action_due)
                                    <div class="text-zinc-700">{{ $opportunity->next_action ?? 'Sem descrição' }}</div>
                                    @if ($opportunity->next_action_due)
                                        <div @class(['text-xs tabular-nums', 'font-semibold text-red-600' => $opportunity->isOverdue(), 'text-zinc-500' => ! $opportunity->isOverdue()])>
                                            {{ $opportunity->isOverdue() ? 'Venceu em' : 'Até' }} {{ $opportunity->next_action_due->format('d/m/Y') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden">{{ $opportunity->executive->user->name }}</flux:table.cell>
                            <flux:table.cell class="max-lg:hidden text-zinc-500">{{ $opportunity->last_activity_at?->diffForHumans() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <livewire:funnel.lead-form />
</div>
