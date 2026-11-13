<div class="flex flex-col gap-6">
    <x-page-header title="Indicadores regionais" subtitle="Moradores e parceiros locais que indicam potenciais clientes.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Novo indicador</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nome ou WhatsApp" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="statusFilter" class="max-w-40" aria-label="Status">
                <flux:select.option value="">Todos os status</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="regionFilter" class="max-w-48" aria-label="Região">
                <flux:select.option value="">Todas as regiões</flux:select.option>
                @foreach ($filterRegions as $region)
                    <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->indicators->isEmpty())
            <x-empty-state icon="map-pin" title="Nenhum indicador cadastrado"
                description="Cadastre quem indica clientes nas cidades. As indicações e comissões passam a ser registradas na Fase 5.">
                <flux:button size="sm" variant="primary" icon="plus" wire:click="create" class="mt-2">Cadastrar indicador</flux:button>
            </x-empty-state>
        @else
            <flux:table :paginate="$this->indicators" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Indicador</flux:table.column>
                    <flux:table.column class="max-md:hidden">Cidade</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Regra de comissão</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column class="max-md:hidden">Acesso</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->indicators as $indicator)
                        <flux:table.row :key="$indicator->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$indicator->name" :src="$indicator->photo_path ? route('photos.indicator', $indicator) : null" />
                                    <div class="min-w-0">
                                        <div class="font-medium text-zinc-900">{{ $indicator->name }}</div>
                                        <a href="{{ \App\Support\Phone::whatsappUrl($indicator->whatsapp) }}" target="_blank" rel="noopener" class="text-xs tabular-nums text-success-500 hover:underline">{{ \App\Support\Phone::format($indicator->whatsapp) }}</a>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden">
                                <div>{{ $indicator->city?->label() ?? '—' }}</div>
                                <div class="text-xs text-zinc-500">{{ $indicator->neighborhood }}{{ $indicator->neighborhood && $indicator->region ? ' · ' : '' }}{{ $indicator->region?->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden text-zinc-600">{{ $indicator->commissionRule?->name ?? 'Sem comissão' }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$indicator->status->color()">{{ $indicator->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-md:hidden">
                                @if ($indicator->hasActiveAccess())
                                    <flux:badge size="sm" color="blue" icon="key">Com acesso</flux:badge>
                                @else
                                    <span class="text-xs text-zinc-400">Sem acesso</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $indicator->id }})" aria-label="Editar {{ $indicator->name }}" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <flux:modal name="indicator-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $editingId ? 'Editar indicador' : 'Novo indicador' }}</flux:heading>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Contato</legend>
                <div class="flex items-center gap-4 sm:col-span-2">
                    @if ($photo && method_exists($photo, 'isPreviewable') && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 rounded-full object-cover ring-1 ring-zinc-200">
                    @elseif ($editingId && ($current = \App\Models\RegionalIndicator::find($editingId))?->photo_path)
                        <img src="{{ route('photos.indicator', $current) }}" alt="" class="size-16 rounded-full object-cover ring-1 ring-zinc-200">
                    @else
                        <x-avatar :name="$form['name'] ?: 'Novo'" size="lg" />
                    @endif
                    <flux:input type="file" wire:model="photo" label="Foto (opcional)" accept="image/jpeg,image/png,image/webp" />
                </div>
                <flux:input wire:model="form.name" label="Nome completo" required class="sm:col-span-2" />
                <flux:input wire:model="form.whatsapp" label="WhatsApp" placeholder="(88) 99999-0000" required />
                <flux:input wire:model="form.phone" label="Outro telefone (opcional)" />
                <flux:input wire:model="form.email" type="email" label="E-mail" class="sm:col-span-2" />
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Localização</legend>
                <flux:select wire:model.live="form.city_id" label="Cidade" required>
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($cities as $city)
                        <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.neighborhood" label="Bairro" />
                <flux:select wire:model="form.region_id" label="Região">
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($regions as $region)
                        <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.referral_channel_id" label="Tipo de indicador">
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($channels as $channel)
                        <flux:select.option :value="$channel->id">{{ $channel->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($cities->isEmpty())
                    <p class="text-xs text-amber-700 sm:col-span-2">Nenhuma cidade marcada como atendida. Marque as cidades em Administração › Regiões e cidades.</p>
                @endif
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Relação comercial</legend>
                <flux:select wire:model="form.commission_rule_id" label="Regra de comissão" description="Sem regra, as indicações não geram comissão.">
                    <flux:select.option value="">Sem comissão</flux:select.option>
                    @foreach ($rules as $rule)
                        <flux:select.option :value="$rule->id">{{ $rule->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.registered_at" type="date" label="Data de cadastro" required />
                <flux:select wire:model="form.status" label="Status">
                    @foreach ($statuses as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex items-end pb-1">
                    <flux:switch wire:model="hasAccess" label="Dar acesso ao sistema" description="Para registrar e acompanhar as próprias indicações." />
                </div>
                <flux:textarea wire:model="form.notes" label="Observações" rows="2" class="sm:col-span-2" />
            </fieldset>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
