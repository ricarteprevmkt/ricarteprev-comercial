<div class="flex flex-col gap-6">
    <x-page-header title="Regiões e cidades" subtitle="Agrupe os municípios atendidos em regiões comerciais. Só cidades atendidas aparecem nos cadastros." />

    <x-card title="Regiões comerciais" :padding="false">
        <x-slot:actions>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="createRegion">Nova região</flux:button>
        </x-slot:actions>

        @if ($this->regions->isEmpty())
            <x-empty-state icon="map" title="Nenhuma região cadastrada" description="Crie regiões como Cariri ou Centro-Sul e depois associe as cidades a elas." />
        @else
            <ul class="grid gap-px bg-zinc-100 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->regions as $region)
                    <li class="flex items-start justify-between gap-3 bg-white px-5 py-4" wire:key="region-{{ $region->id }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-brand-900">{{ $region->name }}</span>
                                @unless ($region->active)
                                    <flux:badge size="sm" color="zinc">Inativa</flux:badge>
                                @endunless
                            </div>
                            <div class="text-sm tabular-nums text-zinc-500">{{ $region->served_cities_count }} {{ $region->served_cities_count === 1 ? 'cidade atendida' : 'cidades atendidas' }}</div>
                            @if ($region->description)
                                <div class="mt-1 text-xs text-zinc-500">{{ $region->description }}</div>
                            @endif
                        </div>
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editRegion({{ $region->id }})" aria-label="Editar {{ $region->name }}" />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card title="Municípios" :description="$servedTotal.' cidade(s) atendida(s) no total. Base oficial do IBGE.'" :padding="false">
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-100 p-4">
            <flux:select wire:model.live="uf" class="max-w-24" aria-label="UF">
                @foreach ($ufs as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar município" class="max-w-xs" aria-label="Buscar município" />
            <flux:select wire:model.live="show" class="max-w-52" aria-label="Exibir">
                <flux:select.option value="todas">Todos os municípios</flux:select.option>
                <flux:select.option value="atendidas">Só atendidos</flux:select.option>
                <flux:select.option value="sem-regiao">Atendidos sem região</flux:select.option>
            </flux:select>
        </div>

        @if (count($selected) > 0)
            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-100 bg-brand-50 px-4 py-3 text-sm">
                <span class="font-medium text-brand-900">{{ count($selected) }} selecionada(s)</span>
                <flux:button size="sm" wire:click="applyBulk('serve')">Marcar como atendidas</flux:button>
                <flux:button size="sm" wire:click="applyBulk('unserve')">Desmarcar</flux:button>
                <flux:select wire:model="bulkRegion" size="sm" class="max-w-48" aria-label="Região para as selecionadas">
                    <flux:select.option value="">Sem região</flux:select.option>
                    @foreach ($this->regions as $region)
                        <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button size="sm" variant="primary" wire:click="applyBulk('region')">Definir região</flux:button>
            </div>
        @endif

        <flux:table :paginate="$this->cities" class="px-4">
            <flux:table.columns>
                <flux:table.column class="w-10"><span class="sr-only">Selecionar</span></flux:table.column>
                <flux:table.column>Município</flux:table.column>
                <flux:table.column class="max-md:hidden">Código IBGE</flux:table.column>
                <flux:table.column>Atendida</flux:table.column>
                <flux:table.column>Região</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->cities as $city)
                    <flux:table.row :key="'city-'.$city->id">
                        <flux:table.cell>
                            <input type="checkbox" value="{{ $city->id }}" wire:model.live="selected" id="sel-{{ $city->id }}" class="size-4 accent-brand-900" aria-label="Selecionar {{ $city->name }}">
                        </flux:table.cell>
                        <flux:table.cell class="font-medium text-zinc-900">{{ $city->name }}</flux:table.cell>
                        <flux:table.cell class="max-md:hidden tabular-nums text-zinc-500">{{ $city->ibge_code }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:switch :checked="$city->served" wire:click="toggleServed({{ $city->id }})" aria-label="{{ $city->name }} atendida" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <select id="region-{{ $city->id }}" wire:change="setCityRegion({{ $city->id }}, $event.target.value)" class="w-44 rounded-lg border border-zinc-200 bg-white px-2 py-1.5 text-sm" aria-label="Região de {{ $city->name }}">
                                <option value="">Sem região</option>
                                @foreach ($this->regions as $region)
                                    <option value="{{ $region->id }}" @selected($city->region_id === $region->id)>{{ $region->name }}</option>
                                @endforeach
                            </select>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-card>

    <flux:modal name="region-form" class="w-full max-w-md">
        <form wire:submit="saveRegion" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editingRegionId ? 'Editar região' : 'Nova região' }}</flux:heading>
            <flux:input wire:model="regionForm.name" label="Nome" placeholder="Ex.: Cariri" required />
            <flux:input wire:model="regionForm.description" label="Descrição (opcional)" />
            <flux:switch wire:model="regionForm.active" label="Região ativa" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
