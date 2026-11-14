<div class="flex flex-col gap-6">
    <x-page-header title="Funil comercial" subtitle="Contatos cadastrados, origem e responsável.">
        <x-slot:actions>
            @can('leads.manage')
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('lead-form:create')">Novo contato</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-funnel-tabs />

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Nome, telefone ou e-mail" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="statusFilter" class="max-w-44" aria-label="Situação">
                <flux:select.option value="">Todas as situações</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
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

        @if ($this->leads->isEmpty())
            <x-empty-state icon="user-plus" title="Nenhum contato encontrado" description="Cadastre o primeiro contato ou ajuste os filtros.">
                @can('leads.manage')
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="$dispatch('lead-form:create')" class="mt-2">Novo contato</flux:button>
                @endcan
            </x-empty-state>
        @else
            <flux:table :paginate="$this->leads" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Contato</flux:table.column>
                    <flux:table.column class="max-md:hidden">Cidade</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Origem</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Responsável</flux:table.column>
                    <flux:table.column>Situação</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->leads as $lead)
                        <flux:table.row :key="$lead->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $lead->name }}</div>
                                <div class="flex flex-wrap gap-x-3 text-xs tabular-nums text-zinc-500">
                                    @if ($lead->phone)<span>{{ \App\Support\Phone::format($lead->phone) }}</span>@endif
                                    @if ($lead->whatsapp)
                                        <a href="{{ \App\Support\Phone::whatsappUrl($lead->whatsapp) }}" target="_blank" rel="noopener" class="text-success-500 hover:underline">WhatsApp</a>
                                    @endif
                                    <span>desde {{ $lead->entered_at->format('d/m/Y') }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden">
                                {{ $lead->city?->label() ?? '—' }}
                                @if ($lead->neighborhood)<div class="text-xs text-zinc-500">{{ $lead->neighborhood }}</div>@endif
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden">
                                {{ $lead->source?->name ?? '—' }}
                                @if ($lead->indicator)<div class="text-xs text-zinc-500">por {{ $lead->indicator->name }}</div>@endif
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden">{{ $lead->executive?->user->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$lead->status->color()">{{ $lead->status->label() }}</flux:badge>
                                @if ($lead->open_opportunities_count)
                                    <div class="mt-0.5 text-xs text-zinc-500">{{ $lead->open_opportunities_count }} em aberto</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $lead)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Ações para {{ $lead->name }}" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="$dispatch('lead-form:edit', { id: {{ $lead->id }} })">Editar contato</flux:menu.item>
                                            <flux:menu.item icon="plus-circle" wire:click="startOpportunity({{ $lead->id }})">Abrir oportunidade</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <livewire:funnel.lead-form />

    <flux:modal name="open-opportunity" class="w-full max-w-lg">
        <form wire:submit="createOpportunity" class="grid gap-4 sm:grid-cols-2">
            <flux:heading size="lg" class="sm:col-span-2">Abrir oportunidade</flux:heading>
            <flux:select wire:model="opportunity.stage" label="Etapa inicial">
                @foreach ($initialStages as $stage)
                    <flux:select.option :value="$stage->value">{{ $stage->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="opportunity.estimated_value" type="number" min="0" step="0.01" label="Valor estimado (R$)" />
            <flux:input wire:model="opportunity.interest" label="Interesse" class="sm:col-span-2" />
            <flux:input wire:model="opportunity.next_action" label="Próxima ação" />
            <flux:input wire:model="opportunity.next_action_due" type="date" label="Prazo" />
            @error('form.executive_id') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2 sm:col-span-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Abrir</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
