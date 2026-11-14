@php
    use App\Support\Money;
    $e = $executive;
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
    $brl = fn ($v) => $v === null ? '—' : Money::format($v);
    $km = fn ($v) => number_format((float) $v, 1, ',', '.').' km';
    $done = $todayVisits->where('status', $doneStatus)->count();
    $planned = $todayVisits->filter(fn ($v) => $v->status->isPlanned())->count();
@endphp

<div class="flex flex-col gap-6">
    <x-page-header :title="$e->user->name" :subtitle="'Desempenho individual · '.trim(($e->city?->label() ?? '').($e->region ? ' · '.$e->region->name : ''), ' ·')" :period="true">
        <x-slot:actions>
            @can('executives.view')
                <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('executives.index')" wire:navigate>Executivos</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        <x-card>
            <div class="flex flex-col items-center gap-2 text-center">
                <x-avatar :name="$e->user->name" size="lg" :src="$e->photo_path ? route('photos.executive', $e) : null" />
                <div class="font-semibold text-brand-900">{{ $e->user->name }}</div>
                <flux:badge size="sm" :color="$e->status->color()">{{ $e->status->label() }}</flux:badge>
            </div>
            <div class="mt-5 flex flex-col gap-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Meta de {{ $goal['month']->translatedFormat('F') }}</div>
                @foreach ([['Visitas', $goal['visits'], $goal['visits_target'], $goal['visits_pct']], ['Contratos', $goal['contracts'], $goal['contracts_target'], $goal['contracts_pct']]] as [$label, $value, $target, $goalPct])
                    <div>
                        <div class="flex justify-between text-sm"><span class="text-zinc-700">{{ $label }}</span><span class="tabular-nums"><strong>{{ $value }}</strong>{{ $target ? ' de '.$target : '' }}</span></div>
                        @if ($target)
                            <div class="mt-1.5 h-2 rounded-full bg-zinc-100"><div @class(['h-full rounded-full', 'bg-success-500' => $goalPct >= $goal['expected_pct'], 'bg-gold-400' => $goalPct < $goal['expected_pct']]) style="width: {{ min(100, $goalPct ?? 0) }}%"></div></div>
                            <div class="mt-0.5 text-xs text-zinc-500">{{ $pct($goalPct) }} · esperado {{ $pct($goal['expected_pct']) }}</div>
                        @else
                            <div class="text-xs text-zinc-400">Sem meta definida</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>

        <div class="grid grid-cols-2 gap-4 xl:grid-cols-3">
            <x-stat label="Visitas realizadas" :value="$s['visits_done']" icon="calendar-days" :delta="$delta($s['visits_done'], $p['visits_done'])" :hint="'de '.$s['visits_planned'].' planejadas'" />
            <x-stat label="Oportunidades" :value="$s['opportunities']" icon="sparkles" :delta="$delta($s['opportunities'], $p['opportunities'])" hint="abertas no período" />
            <x-stat label="Contratos" :value="$s['contracts']" icon="document-check" :delta="$delta($s['contracts'], $p['contracts'])" :hint="$brl($s['contracted']).' contratados'" />
            <x-stat label="Conversão" :value="$pct($s['conversion'])" icon="receipt-percent" hint="contatos → contratos" />
            <x-stat label="Deslocamento" :value="$km($s['km_reported'] > 0 ? $s['km_reported'] : $s['km_estimated'])" icon="map" :hint="$s['km_reported'] > 0 ? 'informado nas visitas (estimado '.$km($s['km_estimated']).')' : 'estimado pela rota'" />
            <x-stat label="Custo por visita" :value="$brl($s['cost_per_visit'])" icon="banknotes" :hint="'por contrato: '.$brl($s['cost_per_contract'])" />
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <x-card title="Evolução de visitas e contratos" :description="$period->label()" class="xl:col-span-3">
            <div wire:ignore x-data="apexChart('visitsContracts', @js($daily), 'exec-daily')"><div x-ref="canvas"></div></div>
        </x-card>

        <x-card title="Planejamento de hoje" :padding="false" class="xl:col-span-2">
            <x-slot:actions>
                <a href="{{ route('agenda.index', ['executivo' => $e->id]) }}" wire:navigate class="text-sm font-medium text-brand-500 hover:underline">Agenda</a>
            </x-slot:actions>
            <div class="flex items-center gap-3 border-b border-zinc-100 px-5 py-3">
                <span class="text-lg font-bold tabular-nums text-brand-900">{{ $done }} / {{ $planned }}</span>
                <div class="h-2 flex-1 rounded-full bg-zinc-100"><div class="h-full rounded-full bg-success-500" style="width: {{ $planned ? $done / $planned * 100 : 0 }}%"></div></div>
            </div>
            <ul class="divide-y divide-zinc-100 text-sm">
                @forelse ($todayVisits as $visit)
                    <li class="flex items-center justify-between gap-2 px-5 py-2.5">
                        <span><span class="font-semibold tabular-nums text-brand-900">{{ $visit->scheduled_at->format('H:i') }}</span> {{ $visit->type->label() }} · {{ $visit->displayName() }} <span class="text-zinc-500">{{ $visit->city?->name }}</span></span>
                        <flux:badge size="sm" :color="$visit->status->color()">{{ $visit->status->label() }}</flux:badge>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-zinc-500">Nenhuma visita hoje.</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Realizado x meta" description="Últimos 6 meses" class="xl:col-span-2">
            <div wire:ignore x-data="apexChart('targets', @js($targets), 'exec-targets')"><div x-ref="canvas"></div></div>
        </x-card>

        <x-card title="Motivos de visitas não realizadas" :description="$period->label()">
            @if (count($reasons) === 0)
                <p class="py-8 text-center text-sm text-zinc-500">Nenhuma visita não realizada no período.</p>
            @else
                <div wire:ignore x-data="apexChart('donut', @js($reasonsChart), 'exec-reasons')"><div x-ref="canvas"></div></div>
            @endif
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Oportunidades em aberto" :padding="false" class="xl:col-span-2">
            <ul class="divide-y divide-zinc-100 text-sm">
                @forelse ($openOpportunities as $o)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5">
                        <div>
                            <a href="{{ route('funnel.opportunities.show', $o) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $o->lead->name }}</a>
                            <div class="text-xs text-zinc-500">{{ $o->next_action ?? 'Sem próxima ação' }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($o->next_action_due)
                                <span @class(['text-xs tabular-nums', 'font-semibold text-red-600' => $o->isOverdue(), 'text-zinc-500' => ! $o->isOverdue()])>{{ $o->next_action_due->format('d/m') }}</span>
                            @endif
                            <flux:badge size="sm" :color="$o->stage->color()">{{ $o->stage->label() }}</flux:badge>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-zinc-500">Nenhuma oportunidade em aberto.</li>
                @endforelse
            </ul>
        </x-card>

        @if ($canCompare)
            <x-card title="Comparativo de desempenho">
                <x-slot:actions>
                    <flux:select wire:model.live="compareBy" size="sm" aria-label="Comparar por">
                        <flux:select.option value="visits">Visitas</flux:select.option>
                        <flux:select.option value="contracts">Contratos</flux:select.option>
                        <flux:select.option value="opportunities">Oportunidades</flux:select.option>
                        <flux:select.option value="conversion">Conversão</flux:select.option>
                    </flux:select>
                </x-slot:actions>
                <div wire:ignore x-data="apexChart('ranking', @js($rankingChart), 'exec-ranking')"><div x-ref="canvas"></div></div>
            </x-card>
        @endif
    </div>
</div>
