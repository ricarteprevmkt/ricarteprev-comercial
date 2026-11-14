<div>
    <flux:modal name="visit-form" class="w-full max-w-2xl" x-on:modal-show.window="$nextTick(() => $dispatch('pin-picker-refresh'))">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $visitId ? 'Editar visita' : 'Agendar visita' }}</flux:heading>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Quem será visitado</legend>

                <div class="relative sm:col-span-2">
                    @if ($form['lead_id'])
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2">
                            <div>
                                <div class="text-xs text-zinc-500">Contato</div>
                                <div class="font-medium text-zinc-900">{{ $leadSearch }}</div>
                            </div>
                            <flux:button size="xs" variant="ghost" wire:click="clearLead">Trocar</flux:button>
                        </div>
                    @else
                        <flux:input wire:model.live.debounce.300ms="leadSearch" label="Contato cadastrado" placeholder="Digite o nome para buscar" icon="magnifying-glass" autocomplete="off" />
                        @if ($leadResults->isNotEmpty())
                            <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg" role="listbox">
                                @foreach ($leadResults as $lead)
                                    <li>
                                        <button type="button" wire:click="selectLead({{ $lead->id }})" class="flex w-full justify-between px-3 py-2 text-start text-sm hover:bg-brand-50" role="option">
                                            <span class="font-medium">{{ $lead->name }}</span>
                                            <span class="text-xs text-zinc-500">{{ $lead->city?->label() }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <flux:input wire:model="form.contact_name" label="Ou nome de quem será visitado (sem cadastro)" class="mt-3" />
                    @endif
                    @error('form.lead_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($opportunities->isNotEmpty())
                    <flux:select wire:model="form.opportunity_id" label="Oportunidade relacionada" class="sm:col-span-2">
                        <flux:select.option value="">Nenhuma</flux:select.option>
                        @foreach ($opportunities as $opportunity)
                            <flux:select.option :value="$opportunity->id">#{{ $opportunity->id }} · {{ $opportunity->stage->label() }}{{ $opportunity->interest ? ' · '.$opportunity->interest : '' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Quando e com quem</legend>
                <flux:input wire:model="form.date" type="date" label="Data" required />
                <flux:input wire:model="form.time" type="time" label="Horário" required />
                <flux:select wire:model="form.type" label="Tipo">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.priority" label="Prioridade">
                    @foreach ($priorities as $priority)
                        <flux:select.option :value="$priority->value">{{ $priority->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.executive_id" label="Executivo" required class="sm:col-span-2" :disabled="$executives->count() === 1">
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </fieldset>

            <fieldset class="grid gap-4 sm:grid-cols-2">
                <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Onde</legend>
                <flux:select wire:model.live="form.city_id" label="Cidade" required>
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($cities as $city)
                        <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.neighborhood" label="Bairro" />
                <flux:input wire:model="form.address" label="Endereço" class="sm:col-span-2" />

                <div class="sm:col-span-2" wire:ignore x-data="pinPicker('form.latitude', 'form.longitude')" x-on:pin-picker-refresh.window="refresh()">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-800">Local no mapa (opcional)</span>
                        <button type="button" x-on:click="clear()" class="text-xs text-brand-500 hover:underline">Limpar ponto</button>
                    </div>
                    <div x-ref="canvas" class="h-56 w-full rounded-lg border border-zinc-200"
                        data-tiles="{{ config('services.map.tiles') }}" data-attribution="{{ config('services.map.attribution') }}"></div>
                    <p class="mt-1 text-xs text-zinc-500">Toque no mapa para marcar o endereço. Sem ponto marcado, a rota usa o centro da cidade (aproximado).</p>
                </div>
                @if ($form['lead_id'])
                    <flux:checkbox wire:model="saveLocationOnLead" label="Guardar este ponto no cadastro do contato" class="sm:col-span-2" />
                @endif
                <flux:textarea wire:model="form.notes" label="Observações" rows="2" class="sm:col-span-2" />
            </fieldset>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $visitId ? 'Salvar' : 'Agendar' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
