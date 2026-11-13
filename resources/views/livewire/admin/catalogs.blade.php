<div class="flex flex-col gap-6">
    <x-page-header title="Catálogos" subtitle="Listas usadas nos formulários: origens, motivos, categorias e canais." />

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        <nav aria-label="Catálogos" class="flex gap-2 overflow-x-auto lg:flex-col">
            @foreach ($catalogs as $catalog)
                <button type="button" wire:click="$set('type', '{{ $catalog->value }}')"
                    @class([
                        'shrink-0 rounded-lg px-3 py-2 text-start text-sm font-medium transition',
                        'bg-brand-900 text-white' => $catalog === $current,
                        'text-zinc-600 hover:bg-white hover:text-brand-900' => $catalog !== $current,
                    ])
                    @if ($catalog === $current) aria-current="page" @endif>
                    {{ $catalog->label() }}
                </button>
            @endforeach
        </nav>

        <x-card :title="$current->label()" :description="$current->description()" :padding="false">
            <x-slot:actions>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="create">Novo item</flux:button>
            </x-slot:actions>

            @if ($this->items->isEmpty())
                <x-empty-state icon="list-bullet" title="Catálogo vazio" description="Adicione o primeiro item." />
            @else
                <ul class="divide-y divide-zinc-100">
                    @foreach ($this->items as $item)
                        <li class="flex items-center gap-4 px-5 py-3" wire:key="{{ $current->value }}-{{ $item->id }}">
                            <span class="w-10 text-xs tabular-nums text-zinc-400">{{ $item->sort_order }}</span>
                            <span @class(['flex-1 font-medium', 'text-zinc-900' => $item->active, 'text-zinc-400 line-through' => ! $item->active])>{{ $item->name }}</span>
                            @if ($current === \App\Enums\CatalogType::ExpenseCategories)
                                <flux:badge size="sm" :color="$item->nature === 'variable' ? 'blue' : 'zinc'">{{ $item->nature === 'variable' ? 'Variável' : 'Fixa' }}</flux:badge>
                            @endif
                            @if ($current === \App\Enums\CatalogType::LossReasons)
                                <span class="text-xs text-zinc-500">{{ ['opportunity' => 'Funil', 'referral' => 'Indicações', 'both' => 'Funil e indicações'][$item->applies_to] ?? '' }}</span>
                            @endif
                            <flux:switch :checked="$item->active" wire:click="toggle({{ $item->id }})" aria-label="{{ $item->name }} ativo" />
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $item->id }})" aria-label="Editar {{ $item->name }}" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <flux:modal name="catalog-form" class="w-full max-w-md">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingId ? 'Editar item' : 'Novo item' }} · {{ $current->label() }}</flux:heading>
            <flux:input wire:model="form.name" label="Nome" required />
            <flux:input wire:model="form.sort_order" type="number" min="0" label="Ordem de exibição" />
            @if ($current === \App\Enums\CatalogType::ExpenseCategories)
                <flux:select wire:model="form.nature" label="Natureza" description="Despesas variáveis entram na margem de contribuição.">
                    <flux:select.option value="variable">Variável</flux:select.option>
                    <flux:select.option value="fixed">Fixa</flux:select.option>
                </flux:select>
            @endif
            @if ($current === \App\Enums\CatalogType::LossReasons)
                <flux:select wire:model="form.applies_to" label="Usado em">
                    <flux:select.option value="both">Funil e indicações</flux:select.option>
                    <flux:select.option value="opportunity">Só no funil</flux:select.option>
                    <flux:select.option value="referral">Só nas indicações</flux:select.option>
                </flux:select>
            @endif
            <flux:switch wire:model="form.active" label="Ativo" description="Itens inativos somem dos formulários, mas continuam nos registros antigos." />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
