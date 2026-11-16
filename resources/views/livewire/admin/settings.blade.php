<div class="flex flex-col gap-6">
    <x-page-header title="Parâmetros" subtitle="Regras comerciais usadas nos cálculos e alertas. Toda alteração fica registrada na auditoria." />

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($groups as $group => $settings)
                <x-card :title="$groupLabels[$group] ?? ucfirst($group)">
                    <div class="flex flex-col gap-5">
                        @if ($group === 'rotas')
                            @if (! $roadRouting['enabled'])
                                <flux:callout icon="information-circle" color="zinc">
                                    <flux:callout.text>Cálculo pelas estradas desligado (falta a chave do OpenRouteService no servidor). As distâncias usam a linha reta × fator de estrada.</flux:callout.text>
                                </flux:callout>
                            @elseif ($roadRouting['paused'])
                                <flux:callout icon="exclamation-triangle" color="amber">
                                    <flux:callout.text>O serviço de rotas não respondeu há pouco. Por alguns minutos, as distâncias usam o fator de estrada.</flux:callout.text>
                                </flux:callout>
                            @else
                                <flux:callout icon="check-circle" color="green">
                                    <flux:callout.text>Distâncias calculadas pelas estradas (OpenRouteService). O fator de estrada só é usado se o serviço ficar indisponível.</flux:callout.text>
                                </flux:callout>
                            @endif
                        @endif
                        @foreach ($settings as $setting)
                            @php($field = 'values.'.\App\Livewire\Admin\Settings::field($setting->key))
                            <div wire:key="setting-{{ $setting->key }}">
                                @if (isset($choices[$setting->key]))
                                    <flux:select wire:model="{{ $field }}" :label="$setting->label" :description="$setting->description">
                                        @foreach ($choices[$setting->key] as $value => $label)
                                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:input
                                        wire:model="{{ $field }}"
                                        :type="in_array($setting->type, ['int', 'decimal']) ? 'number' : 'text'"
                                        :step="$setting->type === 'decimal' ? '0.01' : '1'"
                                        :label="$setting->label"
                                        :description="$setting->description"
                                        :placeholder="$setting->type === 'decimal' ? 'Não informado' : null" />
                                @endif
                                @if ($setting->updater)
                                    <p class="mt-1 text-xs text-zinc-400">Alterado por {{ $setting->updater->name }} em {{ $setting->updated_at->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Salvar parâmetros</flux:button>
        </div>
    </form>
</div>
