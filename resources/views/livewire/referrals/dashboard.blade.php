@php
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $prevLabel = fn (int $n) => 'de '.$n.' no período anterior';
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Parceiros regionais" subtitle="Desempenho das indicações por região e o impacto das parcerias locais." icon="map-pin" :period="true">
        <x-slot:actions>
            <flux:select wire:model.live="regionFilter" size="sm" class="min-w-44" aria-label="Região">
                <flux:select.option value="">Todas as regiões</flux:select.option>
                @foreach ($regions as $region)
                    <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:actions>
    </x-page-header>

    <x-referral-tabs />

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <x-stat label="Total de indicações" :value="$totals['total']" icon="user-group" :delta="$delta($totals['total'], $prev['total'])" :hint="$prevLabel($prev['total'])" />
        <x-stat label="Leads qualificados" :value="$totals['qualified']" icon="check-badge" :delta="$delta($totals['qualified'], $prev['qualified'])" :hint="$prevLabel($prev['qualified'])" />
        <x-stat label="Oportunidades geradas" :value="$totals['opportunities']" icon="sparkles" :delta="$delta($totals['opportunities'], $prev['opportunities'])" :hint="$prevLabel($prev['opportunities'])" />
        <x-stat label="Contratos originados" :value="$contracts" icon="document-check" :delta="$delta($contracts, $prevContracts)" :hint="$brl($revenue).' contratados'" />
        <x-stat label="Comissões previstas" :value="$brl($commissions['expected'])" icon="banknotes" :hint="'aprovadas '.$brl($commissions['approved']).' · pagas '.$brl($commissions['paid'])" />
    </div>

    @if ($totals['duplicates'] > 0)
        <p class="text-sm text-zinc-600">{{ $totals['duplicates'] }} indicação(ões) de contatos já cadastrados no período, registradas como duplicadas e fora da contagem.</p>
    @endif

    @if ($totals['total'] === 0 && $contracts === 0)
        <x-card>
            <x-empty-state icon="map-pin" title="Nenhuma indicação em {{ mb_strtolower($period->label()) }}"
                description="Registre as indicações recebidas dos moradores e parceiros locais. Os números aparecem aqui conforme elas avançam no funil.">
                @can('referrals.create')
                    <flux:button size="sm" variant="primary" icon="plus" :href="route('referrals.list')" wire:navigate class="mt-2">Registrar indicação</flux:button>
                @endcan
            </x-empty-state>
        </x-card>
    @endif

    <div class="grid gap-6 xl:grid-cols-5">
        <x-card title="Desempenho por cidade" description="Indicações, leads e contratos" :padding="false" class="xl:col-span-3">
            @if (count($byCity) === 0)
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Sem indicações no período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs text-zinc-500">
                                <th scope="col" class="px-5 py-2.5 font-medium">Cidade</th>
                                <th scope="col" class="px-3 py-2.5 text-end font-medium">Indicações</th>
                                <th scope="col" class="px-3 py-2.5 text-end font-medium">Qualificados</th>
                                <th scope="col" class="px-3 py-2.5 text-end font-medium">Oportunidades</th>
                                <th scope="col" class="px-3 py-2.5 text-end font-medium">Contratos</th>
                                <th scope="col" class="px-5 py-2.5 text-end font-medium">Conversão</th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums">
                            @foreach ($byCity as $row)
                                <tr class="border-b border-zinc-100 last:border-0">
                                    <td class="px-5 py-2.5 font-medium text-zinc-900">{{ $row['city']->name }}</td>
                                    <td class="px-3 py-2.5 text-end">{{ $row['total'] }}</td>
                                    <td class="px-3 py-2.5 text-end">{{ $row['qualified'] }}</td>
                                    <td class="px-3 py-2.5 text-end">{{ $row['opportunities'] }}</td>
                                    <td class="px-3 py-2.5 text-end font-semibold text-brand-900">{{ $row['signed'] }}</td>
                                    <td class="px-5 py-2.5 text-end">{{ $pct($row['conversion']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Mapa de indicações" description="Concentração por município" :padding="false" class="xl:col-span-2">
            <div wire:ignore x-data="heatMap(@js($mapPoints))">
                <div x-ref="canvas" class="h-72 w-full" data-tiles="{{ config('services.map.tiles') }}" data-attribution="{{ config('services.map.attribution') }}"></div>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 border-t border-zinc-100 px-5 py-3 text-xs text-zinc-600">
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#0b234b]"></span>mais de 25</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#1c3a70]"></span>16 a 25</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#2f6fd0]"></span>8 a 15</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#86a4d4]"></span>1 a 7</span>
            </div>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Indicações por nome" description="Ranking do período (10 primeiros)" :padding="false">
            <ol class="divide-y divide-zinc-100">
                @forelse ($byIndicator as $i => $row)
                    @php($max = $byIndicator[0]['total'] ?: 1)
                    <li class="flex items-center gap-3 px-5 py-2.5">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-900">{{ $i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            @can('indicators.manage')
                                <a href="{{ route('referrals.indicators.show', $row['indicator']) }}" wire:navigate class="block truncate text-sm font-medium text-zinc-900 hover:underline">{{ $row['indicator']->name }}</a>
                            @else
                                <span class="block truncate text-sm font-medium text-zinc-900">{{ $row['indicator']->name }}</span>
                            @endcan
                            <div class="mt-1 h-1.5 rounded-full bg-zinc-100" aria-hidden="true"><div class="h-full rounded-full bg-brand-500" style="width: {{ $row['total'] / $max * 100 }}%"></div></div>
                        </div>
                        <span class="w-16 text-end text-sm tabular-nums"><strong>{{ $row['total'] }}</strong><span class="block text-xs text-zinc-500">{{ $row['signed'] }} contr.</span></span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-zinc-500">Sem indicações no período.</li>
                @endforelse
            </ol>
        </x-card>

        <x-card title="Indicações por canal">
            <ul class="flex flex-col gap-3">
                @forelse ($byChannel as $row)
                    <li>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-700">{{ $row['channel'] }}</span>
                            <span class="tabular-nums"><strong>{{ $row['total'] }}</strong> <span class="text-zinc-500">· {{ $pct($row['share']) }}</span></span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-zinc-100" aria-hidden="true"><div class="h-full rounded-full bg-gold-400" style="width: {{ $row['share'] ?? 0 }}%"></div></div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-zinc-500">Sem indicações no período.</li>
                @endforelse
            </ul>
            <div class="mt-5 flex items-center justify-between rounded-lg bg-brand-50 px-4 py-3">
                <span class="text-sm text-brand-900">Conversão das indicações</span>
                <strong class="tabular-nums text-brand-900">{{ $pct($totals['conversion']) }}</strong>
            </div>
        </x-card>

        <x-card title="Ações recentes" :padding="false">
            <ul class="divide-y divide-zinc-100">
                @forelse ($recent as $item)
                    <li class="px-5 py-3 text-sm">
                        <div class="text-zinc-900">
                            <strong>{{ $item->referral->lead->name }}</strong>
                            <span class="text-zinc-500">({{ $item->referral->indicator->name }})</span>
                        </div>
                        <div class="text-zinc-600">{{ $item->to_status->label() }}{{ $item->note ? ' · '.$item->note : '' }}</div>
                        <div class="text-xs text-zinc-500">{{ $item->created_at->diffForHumans() }} · {{ $item->user?->name ?? 'automático' }}</div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-zinc-500">Nenhuma movimentação ainda.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
