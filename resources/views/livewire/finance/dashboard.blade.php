@php
    use App\Support\Money;
    $brl = fn ($v) => $v === null ? '—' : Money::format($v);
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Custos e resultados" subtitle="Eficiência da operação comercial e retorno sobre o investimento." icon="chart-bar" :period="true">
        <x-slot:actions>
            <flux:select wire:model.live="regionFilter" size="sm" class="min-w-44" aria-label="Região">
                <flux:select.option value="">Todas as regiões</flux:select.option>
                @foreach ($regions as $region)
                    <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:actions>
    </x-page-header>

    <x-finance-tabs />

    @if ($s['pending_count'] > 0)
        <flux:callout icon="clock" color="amber">
            <flux:callout.heading>{{ $s['pending_count'] }} despesa(s) aguardando aprovação · {{ $brl($s['pending']) }}</flux:callout.heading>
            <flux:callout.text>Despesas pendentes ainda não entram nos custos abaixo.</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('finance.expenses', ['situacao' => 'pending'])" wire:navigate>Analisar despesas</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <x-stat label="Custo operacional" :value="$brl($s['operational'])" icon="circle-stack" :delta="$delta($s['operational'], $p['operational'])" :invert-delta="true" hint="despesas aprovadas" />
        <x-stat label="Deslocamentos" :value="$brl($s['travel'])" icon="truck" :delta="$delta($s['travel'], $p['travel'])" :invert-delta="true" hint="combustível, pedágio, estacionamento" />
        <x-stat label="Custo por visita" :value="$brl($s['cost_per_visit'])" icon="map-pin" :delta="$delta($s['cost_per_visit'], $p['cost_per_visit'])" :invert-delta="true" :hint="$s['visits_done'].' visita(s) concluída(s)'" />
        <x-stat label="Custo por contrato" :value="$brl($s['cost_per_contract'])" icon="document-check" :delta="$delta($s['cost_per_contract'], $p['cost_per_contract'])" :invert-delta="true" :hint="$s['contracts_signed'].' contrato(s) assinado(s)'" />
        <x-stat label="Receita contratada" :value="$brl($s['contracted'])" icon="banknotes" :delta="$delta($s['contracted'], $p['contracted'])" :hint="'recebida '.$brl($s['received'])" />
        <x-stat label="Margem de contribuição" :value="$brl($s['margin'])" icon="arrow-trending-up"
            :hint="($s['margin_pct'] !== null ? $pct($s['margin_pct']).' da receita ' : '').($s['margin_basis'] === 'received' ? 'recebida' : 'prevista')" />
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <x-card title="Evolução dos custos e resultados" description="Últimos 6 meses até o fim do período" class="xl:col-span-3">
            <div wire:ignore x-data="apexChart('costRevenue', @js($monthly), 'finance-monthly')"><div x-ref="canvas"></div></div>
        </x-card>

        <x-card title="Margem de contribuição estimada" class="xl:col-span-2">
            <dl class="flex flex-col divide-y divide-zinc-100 text-sm">
                <div class="flex justify-between py-2.5"><dt class="text-zinc-600">Receita {{ $s['margin_basis'] === 'received' ? 'recebida' : 'prevista' }}</dt><dd class="tabular-nums font-medium">{{ $brl($s['margin_revenue']) }}</dd></div>
                <div class="flex justify-between py-2.5"><dt class="text-zinc-600">(−) Despesas variáveis aprovadas</dt><dd class="tabular-nums text-red-700">{{ $brl($s['variable_costs']) }}</dd></div>
                <div class="flex justify-between py-2.5"><dt class="text-zinc-600">(−) Comissões de indicadores</dt><dd class="tabular-nums text-red-700">{{ $brl($s['commissions']) }}</dd></div>
                <div class="flex justify-between py-3 text-base font-semibold"><dt class="text-brand-900">Margem estimada</dt><dd class="tabular-nums text-brand-900">{{ $brl($s['margin']) }}</dd></div>
            </dl>
            <p class="mt-2 text-xs text-zinc-500">
                Despesas fixas (equipe, ferramentas, operacionais) ficam fora da margem. A base de receita é definida em Parâmetros.
                Receita contratada não é dinheiro recebido: recebido no período {{ $brl($s['received']) }}.
            </p>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Distribuição dos custos" description="Despesas aprovadas por categoria">
            @if (count($byCategory) === 0)
                <p class="py-8 text-center text-sm text-zinc-500">Nenhuma despesa aprovada no período.</p>
            @else
                <div wire:ignore x-data="apexChart('donut', @js($categoryDonut), 'finance-categories')"><div x-ref="canvas"></div></div>
            @endif
        </x-card>

        <x-card title="Receita por canal de aquisição" description="Contratos assinados por origem">
            @if (count($bySource) === 0)
                <p class="py-8 text-center text-sm text-zinc-500">Nenhum contrato assinado no período.</p>
            @else
                <div wire:ignore x-data="apexChart('donut', @js($sourceDonut), 'finance-sources')"><div x-ref="canvas"></div></div>
            @endif
        </x-card>

        <x-card title="Custo por região" :padding="false">
            @if (count($byRegion) === 0)
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Sem dados no período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="border-b border-zinc-200 text-left text-xs text-zinc-500">
                            <th scope="col" class="px-5 py-2.5 font-medium">Região</th>
                            <th scope="col" class="px-3 py-2.5 text-end font-medium">Por visita</th>
                            <th scope="col" class="px-5 py-2.5 text-end font-medium">Por contrato</th>
                        </tr></thead>
                        <tbody class="tabular-nums">
                            @foreach ($byRegion as $row)
                                <tr class="border-b border-zinc-100 last:border-0">
                                    <td class="px-5 py-2.5"><div class="font-medium text-zinc-900">{{ $row['region'] }}</div><div class="text-xs text-zinc-500">{{ $brl($row['cost']) }} · {{ $row['visits'] }} vis. · {{ $row['contracts'] }} contr.</div></td>
                                    <td class="px-3 py-2.5 text-end">{{ $brl($row['cost_per_visit']) }}</td>
                                    <td class="px-5 py-2.5 text-end">{{ $brl($row['cost_per_contract']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Comparativo por executivo" :padding="false" class="xl:col-span-2">
            @if (count($byExecutive) === 0)
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Sem dados no período.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="border-b border-zinc-200 text-left text-xs text-zinc-500">
                            <th scope="col" class="px-5 py-2.5 font-medium">Executivo</th>
                            <th scope="col" class="px-3 py-2.5 text-end font-medium">Custos</th>
                            <th scope="col" class="px-3 py-2.5 text-end font-medium">Custo por visita</th>
                            <th scope="col" class="px-3 py-2.5 text-end font-medium">Custo por contrato</th>
                            <th scope="col" class="px-5 py-2.5 text-end font-medium">Receita contratada</th>
                        </tr></thead>
                        <tbody class="tabular-nums">
                            @foreach ($byExecutive as $row)
                                <tr class="border-b border-zinc-100 last:border-0">
                                    <td class="px-5 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <x-avatar :name="$row['executive']->user->name" size="sm" :src="$row['executive']->photo_path ? route('photos.executive', $row['executive']) : null" />
                                            <span class="font-medium text-zinc-900">{{ $row['executive']->user->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-end">{{ $brl($row['cost']) }}</td>
                                    <td class="px-3 py-2.5 text-end">{{ $brl($row['cost_per_visit']) }}</td>
                                    <td class="px-3 py-2.5 text-end">{{ $brl($row['cost_per_contract']) }}</td>
                                    <td class="px-5 py-2.5 text-end font-semibold text-brand-900">{{ $brl($row['contracted']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Despesas por categoria" :padding="false">
            <ul class="divide-y divide-zinc-100 text-sm">
                @forelse ($byCategory as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span class="text-zinc-700">{{ $row['category'] }} @if ($row['nature'] === 'fixed')<span class="text-xs text-zinc-400">(fixa)</span>@endif</span>
                        <span class="tabular-nums"><strong>{{ $brl($row['total']) }}</strong> <span class="text-zinc-500">· {{ $pct($row['share']) }}</span></span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-zinc-500">Nenhuma despesa aprovada no período.</li>
                @endforelse
                <li class="flex justify-between bg-zinc-50 px-5 py-3 font-semibold"><span>Total</span><span class="tabular-nums">{{ $brl($s['operational']) }}</span></li>
            </ul>
        </x-card>
    </div>
</div>
