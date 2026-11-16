@php
    use App\Metrics\FunnelMetrics;
    use App\Support\Money;
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
    $brl = fn ($v) => $v === null ? '—' : Money::format($v);
    $num = fn ($v) => number_format((int) $v, 0, ',', '.');
    $d = fn ($a, $b) => $a === null || $b === null ? null : FunnelMetrics::delta((float) $a, (float) $b);
    $ev = $evolution;
    $toneClasses = ['danger' => 'bg-red-50 text-red-700', 'warning' => 'bg-gold-100 text-gold-700', 'info' => 'bg-brand-50 text-brand-700'];
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Visão geral" :subtitle="$teamView ? 'Acompanhe o desempenho comercial da equipe e o andamento das oportunidades.' : 'Seus números, sua agenda e suas pendências.'" icon="home" :period="true">
        <x-slot:actions>
            @if ($executives->isNotEmpty())
                <flux:select wire:model.live="executiveFilter" size="sm" class="min-w-48" aria-label="Executivo">
                    <flux:select.option value="">Toda a equipe</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @if (! $teamView && $ownExecutive)
                <flux:button size="sm" icon="chart-bar" :href="route('executives.show', $ownExecutive)" wire:navigate>Meu desempenho</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Indicadores principais --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <x-stat label="Visitas realizadas" :value="$num($agenda['done'])" icon="calendar-days" :delta="$d($agenda['done'], $prevAgenda['done'])" :hint="'de '.$num($agenda['planned']).' planejadas'" />
        <x-stat label="Oportunidades" :value="$num($funnel['base'])" icon="sparkles" :delta="$d($funnel['base'], $prevFunnel['base'])" hint="abertas no período" />
        <x-stat label="Contratos assinados" :value="$num($finance['contracts_signed'])" icon="document-check" :delta="$d($finance['contracts_signed'], $prevFinance['contracts_signed'])" :hint="$brl($finance['contracted']).' contratados'" />
        <x-stat label="Custo por contrato" :value="$brl($finance['cost_per_contract'])" icon="circle-stack" :delta="$d($finance['cost_per_contract'], $prevFinance['cost_per_contract'])" :invert-delta="true" hint="custo comercial médio" />
        <x-stat label="Taxa de conversão" :value="$pct($funnel['overall'])" icon="receipt-percent"
            :hint="$funnel['overall'] !== null && $prevFunnel['overall'] !== null ? (($funnel['overall'] - $prevFunnel['overall'] >= 0 ? '+' : '').number_format($funnel['overall'] - $prevFunnel['overall'], 1, ',', '.').' p.p. vs. anterior') : 'contatos → contratos'" />
    </div>

    @if ($ownGoal && ($ownGoal['visits_target'] || $ownGoal['contracts_target']))
        <x-card title="Minha meta de {{ $ownGoal['month']->translatedFormat('F') }}">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([['Visitas', $ownGoal['visits'], $ownGoal['visits_target'], $ownGoal['visits_pct']], ['Contratos', $ownGoal['contracts'], $ownGoal['contracts_target'], $ownGoal['contracts_pct']]] as [$label, $done, $target, $p])
                    @if ($target)
                        <div>
                            <div class="flex justify-between text-sm"><span class="text-zinc-700">{{ $label }}: <strong>{{ $done }}</strong> de {{ $target }}</span><span class="tabular-nums">{{ $pct($p) }}</span></div>
                            <div class="mt-1.5 h-2 rounded-full bg-zinc-100"><div @class(['h-full rounded-full', 'bg-success-500' => $p >= $ownGoal['expected_pct'], 'bg-gold-400' => $p < $ownGoal['expected_pct']]) style="width: {{ min(100, $p ?? 0) }}%"></div></div>
                        </div>
                    @endif
                @endforeach
            </div>
            <p class="mt-3 text-xs text-zinc-500">Ritmo esperado até hoje: {{ $pct($ownGoal['expected_pct']) }} da meta.</p>
        </x-card>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Evolução comercial --}}
        <x-card title="Evolução comercial" description="Volume por etapa no período" class="xl:col-span-2">
            <div class="grid gap-4 lg:grid-cols-[1fr_13rem]">
                <div>
                    <x-funnel-chart :stages="$funnelChart" />
                    <p class="mt-3 text-xs text-zinc-500">À direita, quanto cada etapa representa da anterior.</p>
                </div>
                <div class="flex flex-col gap-3 rounded-lg bg-zinc-50 p-4">
                    <div>
                        <div class="text-xs text-zinc-500">Visitas → oportunidades</div>
                        <div class="text-xl font-bold tabular-nums text-brand-900">{{ $pct($ev['visit_to_opportunity']) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Propostas → contratos</div>
                        <div class="text-xl font-bold tabular-nums text-brand-900">{{ $pct($ev['proposal_to_contract']) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Conversão total (contatos → contratos)</div>
                        <div class="text-xl font-bold tabular-nums text-brand-900">{{ $pct($ev['overall']) }}</div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Visão do dia --}}
        <x-card title="Visão do dia" :description="now()->translatedFormat('d \d\e F \d\e Y')">
            <dl class="flex flex-col divide-y divide-zinc-100 text-sm">
                <div class="flex items-center justify-between py-2.5">
                    <dt class="text-zinc-600">Visitas programadas</dt>
                    <dd class="text-end"><strong class="text-brand-900">{{ $todayAgenda['planned'] }}</strong> <span class="text-zinc-500">· {{ $todayAgenda['done'] }} realizadas</span></dd>
                </div>
                <div class="flex items-center justify-between py-2.5">
                    <dt class="text-zinc-600">Deslocamento estimado</dt>
                    <dd class="text-end tabular-nums"><strong class="text-brand-900">{{ number_format($todayAgenda['km_estimated'], 1, ',', '.') }} km</strong>@if ($todayAgenda['cost_estimated'] !== null)<span class="block text-xs text-zinc-500">{{ $brl($todayAgenda['cost_estimated']) }} estimado</span>@endif</dd>
                </div>
                <div class="flex items-center justify-between py-2.5">
                    <dt class="text-zinc-600">Prazos vencidos</dt>
                    <dd><a href="{{ route('funnel.opportunities', ['etapa' => 'atrasadas']) }}" wire:navigate @class(['font-semibold tabular-nums hover:underline', 'text-red-600' => $overdueCount > 0, 'text-zinc-700' => $overdueCount === 0])>{{ $overdueCount }}</a></dd>
                </div>
                <div class="py-2.5">
                    <dt class="text-zinc-600">Compromissos nas próximas 2 horas</dt>
                    <dd class="mt-1">
                        @forelse ($upcoming as $visit)
                            <div class="flex justify-between text-sm"><span>{{ $visit->scheduled_at->format('H:i') }} · {{ $visit->displayName() }}</span>@if ($teamView)<span class="text-xs text-zinc-500">{{ $visit->executive->user->name }}</span>@endif</div>
                        @empty
                            <span class="text-zinc-500">Nenhum</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
            <a href="{{ route('agenda.index') }}" wire:navigate class="mt-2 inline-block text-sm font-medium text-brand-500 hover:underline">Abrir agenda</a>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        @if ($teamView)
            <x-card title="Executivos comerciais" description="Desempenho no período" :padding="false" class="xl:col-span-2">
                @if (count($ranking) === 0)
                    <p class="px-5 py-8 text-center text-sm text-zinc-500">Nenhum executivo cadastrado.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead><tr class="border-b border-zinc-200 text-left text-xs text-zinc-500">
                                <th scope="col" class="px-5 py-2.5 font-medium">Executivo</th>
                                <th scope="col" class="px-3 py-2.5 text-end font-medium">Visitas</th>
                                <th scope="col" class="px-3 py-2.5 text-end font-medium">Contratos</th>
                                <th scope="col" class="px-3 py-2.5 text-end font-medium">Conversão</th>
                                <th scope="col" class="px-5 py-2.5 text-end font-medium">Contratos vs. anterior</th>
                            </tr></thead>
                            <tbody class="tabular-nums">
                                @foreach ($ranking as $row)
                                    <tr class="border-b border-zinc-100 last:border-0">
                                        <td class="px-5 py-2.5">
                                            <a href="{{ route('executives.show', $row['executive']) }}" wire:navigate class="flex items-center gap-2 hover:underline">
                                                <x-avatar :name="$row['executive']->user->name" size="sm" :src="$row['executive']->photo_path ? route('photos.executive', $row['executive']) : null" />
                                                <span class="font-medium text-zinc-900">{{ $row['executive']->user->name }}</span>
                                            </a>
                                        </td>
                                        <td class="px-3 py-2.5 text-end">{{ $row['visits'] }}</td>
                                        <td class="px-3 py-2.5 text-end font-semibold text-brand-900">{{ $row['contracts'] }}</td>
                                        <td class="px-3 py-2.5 text-end">{{ $pct($row['conversion']) }}</td>
                                        <td class="px-5 py-2.5 text-end">
                                            @if ($row['delta'] !== null)
                                                <span @class(['font-semibold', 'text-success-500' => $row['delta'] >= 0, 'text-red-600' => $row['delta'] < 0])>{{ $row['delta'] >= 0 ? '↑' : '↓' }} {{ number_format(abs($row['delta']), 1, ',', '.') }}%</span>
                                            @else
                                                <span class="text-zinc-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-zinc-100 px-5 py-3"><a href="{{ route('executives.index') }}" wire:navigate class="text-sm font-medium text-brand-500 hover:underline">Ver desempenho completo</a></div>
                @endif
            </x-card>
        @endif

        <x-card title="Alertas e pendências" description="Ações que precisam de atenção" :padding="false" :class="$teamView ? '' : 'xl:col-span-3'">
            <ul class="divide-y divide-zinc-100">
                @forelse ($alerts as $alert)
                    <li>
                        <a href="{{ $alert['url'] }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-zinc-50">
                            <span class="flex min-w-9 items-center justify-center rounded-lg px-2 py-1 text-base font-bold tabular-nums {{ $toneClasses[$alert['tone']] }}">{{ $alert['count'] }}</span>
                            <span class="flex-1 text-sm text-zinc-800">{{ $alert['label'] }}</span>
                            <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                        </a>
                    </li>
                @empty
                    <li class="flex items-center gap-3 px-5 py-6 text-sm text-zinc-600">
                        <flux:icon.check-circle class="size-5 text-success-500" /> Nenhuma pendência no momento.
                    </li>
                @endforelse
            </ul>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Oportunidades em andamento" description="Últimas movimentações" :padding="false" class="xl:col-span-2">
            @if ($inProgress->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Nenhuma oportunidade em aberto.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="border-b border-zinc-200 text-left text-xs text-zinc-500">
                            <th scope="col" class="px-5 py-2.5 font-medium">Cliente</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">Etapa</th>
                            <th scope="col" class="px-3 py-2.5 font-medium max-md:hidden">Última atualização</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Próxima ação</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($inProgress as $o)
                                <tr class="border-b border-zinc-100 last:border-0">
                                    <td class="px-5 py-2.5">
                                        <a href="{{ route('funnel.opportunities.show', $o) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $o->lead->name }}</a>
                                        @if ($teamView)<div class="text-xs text-zinc-500">{{ $o->executive->user->name }}</div>@endif
                                    </td>
                                    <td class="px-3 py-2.5"><flux:badge size="sm" :color="$o->stage->color()">{{ $o->stage->label() }}</flux:badge></td>
                                    <td class="px-3 py-2.5 text-zinc-500 max-md:hidden">{{ $o->last_activity_at?->format('d/m/Y') }}</td>
                                    <td class="px-5 py-2.5">
                                        <div class="text-zinc-700">{{ $o->next_action ?? '—' }}</div>
                                        @if ($o->next_action_due)
                                            <div @class(['text-xs tabular-nums', 'font-semibold text-red-600' => $o->isOverdue(), 'text-zinc-500' => ! $o->isOverdue()])>até {{ $o->next_action_due->format('d/m') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-zinc-100 px-5 py-3"><a href="{{ route('funnel.opportunities') }}" wire:navigate class="text-sm font-medium text-brand-500 hover:underline">Ver todas as oportunidades</a></div>
            @endif
        </x-card>

        <x-card title="Desempenho por cidade" description="Visitas realizadas e oportunidades abertas">
            <ul class="flex flex-col gap-3">
                @forelse ($byCity as $row)
                    @php($max = max(1, $byCity[0]['visits'], $byCity[0]['opportunities']))
                    <li>
                        <div class="flex justify-between text-sm"><span class="text-zinc-700">{{ $row['city'] }}</span><span class="tabular-nums text-zinc-600"><strong class="text-brand-900">{{ $row['visits'] }}</strong> vis. · {{ $row['opportunities'] }} oport.</span></div>
                        <div class="mt-1 h-2 rounded-full bg-zinc-100" aria-hidden="true"><div class="h-full rounded-full bg-brand-500" style="width: {{ $row['visits'] / $max * 100 }}%"></div></div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-zinc-500">Sem visitas ou oportunidades no período.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
