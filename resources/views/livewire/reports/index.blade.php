@php($numeric = fn ($f) => in_array($f, ['int', 'money', 'pct', 'decimal'], true))

<div class="flex flex-col gap-6">
    <x-page-header title="Relatórios" subtitle="Escolha o relatório, ajuste os filtros e exporte em PDF, Excel ou CSV." icon="document-chart-bar" :period="true" />

    <div class="grid gap-6 lg:grid-cols-[17rem_1fr]">
        <nav aria-label="Relatórios" class="flex flex-col gap-1">
            @foreach ($reports as $i => $item)
                <button type="button" wire:click="select('{{ $item->key() }}')"
                    @class([
                        'flex gap-2 rounded-lg px-3 py-2 text-start text-sm transition',
                        'bg-brand-900 font-medium text-white' => $item->key() === $definition->key(),
                        'text-zinc-700 hover:bg-white hover:text-brand-900' => $item->key() !== $definition->key(),
                    ])
                    @if ($item->key() === $definition->key()) aria-current="page" @endif>
                    <span class="w-5 shrink-0 tabular-nums opacity-60">{{ $i + 1 }}.</span>
                    <span>{{ $item->title() }}</span>
                </button>
            @endforeach
        </nav>

        <div class="flex min-w-0 flex-col gap-4">
            <x-card :title="$definition->title()" :description="$definition->description()">
                <x-slot:actions>
                    @if ($canExport)
                        @foreach (['pdf' => 'PDF', 'xlsx' => 'Excel', 'csv' => 'CSV'] as $format => $label)
                            <flux:button size="sm" icon="arrow-down-tray" :href="route('reports.export', ['report' => $definition->key(), 'format' => $format] + $exportQuery)" target="_blank">{{ $label }}</flux:button>
                        @endforeach
                    @endif
                </x-slot:actions>

                <div class="flex flex-wrap items-end gap-3">
                    @if (in_array('executive', $available, true) && $executives->isNotEmpty())
                        <flux:select wire:model.live="filters.executive" size="sm" label="Executivo" class="min-w-44">
                            <flux:select.option value="">Todos</flux:select.option>
                            @foreach ($executives as $executive)
                                <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('region', $available, true) && $regions->isNotEmpty())
                        <flux:select wire:model.live="filters.region" size="sm" label="Região" class="min-w-40">
                            <flux:select.option value="">Todas</flux:select.option>
                            @foreach ($regions as $region)
                                <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('city', $available, true))
                        <flux:select wire:model.live="filters.city" size="sm" label="Cidade" class="min-w-44">
                            <flux:select.option value="">Todas</flux:select.option>
                            @foreach ($cities as $city)
                                <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('indicator', $available, true))
                        <flux:select wire:model.live="filters.indicator" size="sm" label="Parceiro" class="min-w-44">
                            <flux:select.option value="">Todos</flux:select.option>
                            @foreach ($indicators as $indicator)
                                <flux:select.option :value="$indicator->id">{{ $indicator->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('source', $available, true))
                        <flux:select wire:model.live="filters.source" size="sm" label="Origem" class="min-w-40">
                            <flux:select.option value="">Todas</flux:select.option>
                            @foreach ($sources as $source)
                                <flux:select.option :value="$source->id">{{ $source->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('stage', $available, true))
                        <flux:select wire:model.live="filters.stage" size="sm" label="Etapa" class="min-w-48">
                            <flux:select.option value="">Todas</flux:select.option>
                            @foreach ($stages as $stage)
                                <flux:select.option :value="$stage->value">{{ $stage->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    <flux:button size="sm" variant="ghost" wire:click="clearFilters">Limpar filtros</flux:button>
                </div>

                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                    @foreach ($filterLabels as $label => $value)
                        <span><strong class="text-zinc-700">{{ $label }}:</strong> {{ $value }}</span>
                    @endforeach
                    <span><strong class="text-zinc-700">Registros:</strong> {{ $total }}</span>
                </div>
            </x-card>

            <x-card :padding="false">
                @if ($total === 0)
                    <x-empty-state icon="document-magnifying-glass" title="Nenhum registro" description="Ajuste o período ou os filtros." />
                @else
                    <div class="overflow-x-auto" wire:loading.class="opacity-50">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-left text-xs text-zinc-500">
                                    @foreach ($columns as [$label, $format])
                                        <th scope="col" @class(['whitespace-nowrap px-4 py-2.5 font-medium', 'text-end' => $numeric($format)])>{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr class="border-b border-zinc-100 last:border-0">
                                        @foreach ($columns as $key => [$label, $format])
                                            <td @class(['px-4 py-2', 'text-end tabular-nums whitespace-nowrap' => $numeric($format)])>{{ \App\Reports\Report::format($row[$key] ?? null, $format) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            @if ($totals && $total <= \App\Livewire\Reports\Index::PREVIEW_LIMIT)
                                <tfoot>
                                    <tr class="bg-gold-100/60 font-semibold">
                                        @foreach ($columns as $key => [$label, $format])
                                            <td @class(['px-4 py-2.5', 'text-end tabular-nums' => $numeric($format)])>
                                                @if (array_key_exists($key, $totals))
                                                    {{ is_string($totals[$key]) ? $totals[$key] : \App\Reports\Report::format($totals[$key], $format) }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                    @if ($total > \App\Livewire\Reports\Index::PREVIEW_LIMIT)
                        <p class="border-t border-zinc-100 px-4 py-3 text-xs text-zinc-500">Mostrando {{ \App\Livewire\Reports\Index::PREVIEW_LIMIT }} de {{ $total }} linhas. A exportação traz tudo (o PDF, até {{ \App\Reports\ReportExporter::PDF_ROW_LIMIT }} linhas).</p>
                    @endif
                @endif
            </x-card>
        </div>
    </div>
</div>
