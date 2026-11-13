<div class="flex flex-col gap-6">
    <x-page-header title="Regras de comissão" subtitle="Como e quando um indicador tem direito a comissão. Sem regra associada, a indicação não gera comissão.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Nova regra</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        @if ($this->rules->isEmpty())
            <x-empty-state icon="banknotes" title="Nenhuma regra de comissão"
                description="Cadastre as regras da política comercial da Ricarte Prev, por exemplo um valor fixo por contrato assinado." />
        @else
            <flux:table class="px-4">
                <flux:table.columns>
                    <flux:table.column>Regra</flux:table.column>
                    <flux:table.column class="max-md:hidden">Cálculo</flux:table.column>
                    <flux:table.column align="end">Indicadores</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rules as $rule)
                        <flux:table.row :key="$rule->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $rule->name }}</div>
                                @if ($rule->description)
                                    <div class="max-w-sm truncate text-xs text-zinc-500">{{ $rule->description }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden text-zinc-600">{{ $rule->summary() }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $rule->indicators_count }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$rule->active ? 'green' : 'zinc'">{{ $rule->active ? 'Ativa' : 'Inativa' }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $rule->id }})" aria-label="Editar {{ $rule->name }}" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <flux:modal name="rule-form" class="w-full max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? 'Editar regra' : 'Nova regra de comissão' }}</flux:heading>

            <flux:input wire:model="form.name" label="Nome da regra" placeholder="Ex.: Indicação padrão por contrato" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form.type" label="Tipo">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.value" type="number" min="0" step="0.01" :label="$form['type'] === 'percent' ? 'Percentual' : 'Valor (R$)'" required />
            </div>

            @if ($form['type'] === 'percent')
                <flux:select wire:model="form.base" label="Aplicado sobre">
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($bases as $base)
                        <flux:select.option :value="$base->value">{{ $base->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model="form.trigger_event" label="Quando gera direito à comissão">
                @foreach ($triggers as $trigger)
                    <flux:select.option :value="$trigger->value">{{ $trigger->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="form.description" label="Descrição (opcional)" rows="2" />
            <flux:switch wire:model="form.active" label="Regra ativa" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
