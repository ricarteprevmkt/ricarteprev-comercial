@php
    $fmt = function (?float $v, string $format): string {
        if ($v === null) {
            return '—';
        }

        return match ($format) {
            'money' => 'R$ '.number_format($v, 0, ',', '.'),
            'pct' => number_format($v, 1, ',', '.').'%',
            'days' => number_format($v, 1, ',', '.').' dias',
            default => number_format($v, 0, ',', '.'),
        };
    };
    $sparkColor = fn (array $k) => $k['delta'] === null ? '#86a4d4' : ((($k['delta'] >= 0) xor $k['invert']) ? '#009a66' : '#dc2626');
    $hero = array_slice($kpis, 0, 4);
    $minor = array_slice($kpis, 4);
    $trendTabs = ['contracts' => 'Contratos', 'contracted' => 'Receita contratada', 'received' => 'Receita recebida', 'visits' => 'Visitas', 'opportunities' => 'Oportunidades', 'prospects' => 'Interessados'];
    $toneClasses = ['good' => 'border-success-500/30 bg-success-50 text-success-500', 'bad' => 'border-red-200 bg-red-50 text-red-700', 'warn' => 'border-gold-400/40 bg-gold-100 text-gold-700', 'info' => 'border-brand-200 bg-brand-50 text-brand-700'];
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Dashboard" :subtitle="$teamView ? 'Os números mais importantes da operação, com tendência e projeção.' : 'Seus números, com tendência e projeção.'" icon="presentation-chart-line" :period="true">
        <x-slot:actions>
            @if ($executives->isNotEmpty())
                <flux:select wire:model.live="executiveFilter" size="sm" class="w-48!" aria-label="Executivo">
                    <flux:select.option value="">Toda a equipe</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:select wire:model.live="window" size="sm" class="w-52!" aria-label="Janela da tendência">
                @foreach (\App\Livewire\Dashboard::WINDOWS as $value => $label)
                    <flux:select.option :value="$value">Tendência: {{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:actions>
    </x-page-header>

    <div wire:loading.class="opacity-60" wire:target="executiveFilter,window,periodChanged" class="flex flex-col gap-6 transition-opacity duration-300">

        {{-- Leituras automáticas --}}
        @if ($insights !== [])
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($insights as $i => $insight)
                    <div wire:key="ins-{{ $chartKey }}-{{ $i }}" class="dash-in flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $toneClasses[$insight['tone']] }}" style="animation-delay: {{ $i * 70 }}ms">
                        <flux:icon :name="$insight['icon']" class="mt-0.5 size-5 shrink-0" />
                        <p class="leading-snug text-zinc-800">{{ $insight['text'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Indicadores principais --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($hero as $i => $k)
                <div wire:key="hero-{{ $chartKey }}-{{ $k['key'] }}" class="dash-in group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:-translate-y-0.5 hover:shadow-md" style="animation-delay: {{ $i * 60 }}ms">
                    <div class="pointer-events-none absolute -right-8 -top-8 size-28 rounded-full bg-brand-50 opacity-70 transition group-hover:scale-110"></div>
                    <div class="relative flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2 text-sm font-medium text-zinc-600">
                            <span class="flex size-8 items-center justify-center rounded-full bg-brand-900 text-white"><flux:icon :name="$k['icon']" class="size-4" /></span>
                            {{ $k['label'] }}
                        </span>
                        @if ($k['delta'] !== null)
                            @php($good = ($k['delta'] >= 0) xor $k['invert'])
                            <span @class(['flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums', 'bg-success-50 text-success-500' => $good, 'bg-red-50 text-red-600' => ! $good])
                                title="Em relação ao período anterior ({{ $fmt($k['previous'], $k['format']) }})">
                                <flux:icon :name="$k['delta'] >= 0 ? 'arrow-up' : 'arrow-down'" variant="micro" />{{ number_format(abs($k['delta']), 1, ',', '.') }}%
                            </span>
                        @endif
                    </div>
                    <div class="relative mt-3 text-3xl font-bold tracking-tight text-brand-900 tabular-nums" x-data="countUp(@js($k['value']), @js($k['format']))" x-text="text">{{ $fmt($k['value'], $k['format']) }}</div>
                    <div class="relative text-xs text-zinc-500">{{ $k['hint'] }}</div>
                    @if (count($k['spark']) > 1)
                        <div wire:ignore class="relative -mx-2 -mb-3 mt-2" x-data="apexChart('spark', @js(['values' => $k['spark'], 'color' => $sparkColor($k)]))"><div x-ref="canvas"></div></div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($minor as $i => $k)
                <div wire:key="minor-{{ $chartKey }}-{{ $k['key'] }}" class="dash-in rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-brand-200 hover:shadow-sm" style="animation-delay: {{ 240 + $i * 50 }}ms">
                    <div class="flex items-center gap-1.5 text-xs font-medium text-zinc-500"><flux:icon :name="$k['icon']" class="size-4 text-brand-700" />{{ $k['label'] }}</div>
                    <div class="mt-1.5 whitespace-nowrap text-xl font-bold text-brand-900 tabular-nums" x-data="countUp(@js($k['value']), @js($k['format']))" x-text="text">{{ $fmt($k['value'], $k['format']) }}</div>
                    <div class="mt-0.5 flex items-center gap-2 text-[11px]">
                        @if ($k['delta'] !== null)
                            @php($good = ($k['delta'] >= 0) xor $k['invert'])
                            <span @class(['shrink-0 font-semibold tabular-nums', 'text-success-500' => $good, 'text-red-600' => ! $good]) title="Em relação ao período anterior">{{ $k['delta'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($k['delta']), 1, ',', '.') }}%</span>
                        @endif
                        <span class="truncate text-zinc-400" title="{{ $k['hint'] }}">{{ $k['hint'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tendência com projeção + meta do mês --}}
        <div class="grid gap-6 xl:grid-cols-3">
            <x-card class="xl:col-span-2" :padding="false">
                <div wire:key="trend-{{ $chartKey }}" x-data="trendChart(@js($trends), 'contracted')">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-brand-900">Evolução e projeção</h2>
                            <p class="mt-0.5 text-sm text-zinc-500">Realizado mês a mês e a projeção: o mês atual pelo ritmo até hoje, os próximos 3 meses pela tendência.</p>
                        </div>
                        <div class="rounded-lg bg-gold-100 px-3 py-1.5 text-right">
                            <div class="text-[11px] font-medium uppercase tracking-wide text-gold-700">Projeção do mês</div>
                            <div class="text-lg font-bold tabular-nums text-brand-900"
                                x-text="current().money ? 'R$ ' + Math.round(current().projected_month).toLocaleString('pt-BR') : Math.round(current().projected_month).toLocaleString('pt-BR')"></div>
                        </div>
                    </div>
                    <div class="flex gap-1 overflow-x-auto px-5 pt-3" role="tablist" aria-label="Indicador">
                        @foreach ($trendTabs as $key => $label)
                            <button type="button" role="tab" x-on:click="key = @js($key)" :aria-selected="key === @js($key)"
                                class="shrink-0 rounded-full px-3 py-1 text-xs font-medium transition"
                                :class="key === @js($key) ? 'bg-brand-900 text-white shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200'">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div wire:ignore class="px-3 pb-2"><div x-ref="canvas"></div></div>
                </div>
            </x-card>

            <x-card :title="'Meta de '.$goal['month']->translatedFormat('F')" :description="'Já se passaram '.number_format($goal['elapsed_pct'], 0, ',', '.').'% do mês.'">
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($goal['items'] as $item)
                        <div wire:key="goal-{{ $chartKey }}-{{ $item['label'] }}" class="flex flex-col items-center text-center">
                            @if ($item['target'])
                                <div wire:ignore class="-my-3 w-full" x-data="apexChart('radial', @js(['pct' => $item['pct'], 'expected' => $goal['elapsed_pct'], 'label' => $item['label']]))"><div x-ref="canvas"></div></div>
                                <div class="text-sm text-zinc-700"><strong class="tabular-nums">{{ $item['done'] }}</strong> de {{ $item['target'] }}</div>
                                <div @class(['mt-1 rounded-full px-2 py-0.5 text-xs font-medium', 'bg-success-50 text-success-500' => $item['projected_pct'] >= 100, 'bg-gold-100 text-gold-700' => $item['projected_pct'] < 100])>
                                    projeção {{ number_format($item['projected'], 0, ',', '.') }} ({{ number_format($item['projected_pct'], 0, ',', '.') }}%)
                                </div>
                            @else
                                <div class="flex h-40 w-full flex-col items-center justify-center rounded-xl bg-zinc-50 px-3">
                                    <span class="text-sm font-medium text-zinc-700">{{ $item['label'] }}: {{ $item['done'] }}</span>
                                    <span class="mt-1 text-xs text-zinc-500">projeção {{ number_format($item['projected'], 0, ',', '.') }} · sem meta cadastrada</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-zinc-500">Verde: acima do ritmo esperado para hoje. As metas são as dos executivos (cadastro em Executivos).</p>
            </x-card>
        </div>

        {{-- Funil e origem --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="Funil comercial" description="Oportunidades abertas no período e até onde chegaram.">
                @if (array_sum(array_column($funnel, 'count')) === 0)
                    <x-empty-state icon="funnel" title="Sem oportunidades no período" />
                @else
                    <div wire:key="funnel-{{ $chartKey }}" wire:ignore x-data="apexChart('funnel', @js(['labels' => array_column($funnel, 'label'), 'values' => array_column($funnel, 'count')]))"><div x-ref="canvas"></div></div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-500">
                        @foreach (array_slice($funnel, 1) as $s)
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5">{{ $s['label'] }}: <strong class="text-zinc-700">{{ $s['conversion'] === null ? '—' : number_format($s['conversion'], 1, ',', '.').'%' }}</strong> da etapa anterior</span>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card title="Receita por origem" description="De onde vêm os contratos assinados no período.">
                @if (array_sum($sources['values']) <= 0)
                    <x-empty-state icon="chart-pie" title="Sem contratos no período" />
                @else
                    <div wire:key="src-{{ $chartKey }}" wire:ignore x-data="apexChart('donut', @js(['labels' => $sources['labels'], 'values' => $sources['values'], 'total_label' => 'Contratado']))"><div x-ref="canvas"></div></div>
                @endif
            </x-card>
        </div>

        {{-- Dinheiro --}}
        <x-card title="Receita e custos" description="Receita contratada e recebida por mês, com os custos comerciais aprovados.">
            <div wire:key="money-{{ $chartKey }}" wire:ignore x-data="apexChart('moneyFlow', @js($costRevenue))"><div x-ref="canvas"></div></div>
        </x-card>

        {{-- Equipe e agenda --}}
        <div class="grid gap-6 lg:grid-cols-2">
            @if ($teamView && count($ranking['labels']) > 1)
                <x-card title="Desempenho por executivo" description="Contratos e receita contratada no período.">
                    <div wire:key="rank-{{ $chartKey }}" wire:ignore x-data="apexChart('rankingDual', @js($ranking))"><div x-ref="canvas"></div></div>
                </x-card>
            @endif
            <x-card title="Quando as visitas acontecem" description="Visitas realizadas no período por dia da semana e horário." :class="$teamView && count($ranking['labels']) > 1 ? '' : 'lg:col-span-2'">
                <div wire:key="heat-{{ $chartKey }}" wire:ignore x-data="apexChart('heatmap', @js($heatmap))"><div x-ref="canvas"></div></div>
            </x-card>
        </div>

        {{-- Interessados e INSS --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="Interessados por semana" description="Chegaram pelo WhatsApp, qualificados e descartados nas últimas 12 semanas." :class="$inss === null ? 'lg:col-span-2' : ''">
                <div wire:key="pros-{{ $chartKey }}" wire:ignore x-data="apexChart('prospects', @js($prospects))"><div x-ref="canvas"></div></div>
            </x-card>
            @if ($inss !== null)
                <x-card title="Resultado no INSS" description="Decisões por mês dos protocolos do escritório (lidos do C2) e taxa de deferimento.">
                    <div wire:key="inss-{{ $chartKey }}" wire:ignore x-data="apexChart('inss', @js($inss))"><div x-ref="canvas"></div></div>
                </x-card>
            @endif
        </div>

        <p class="text-xs text-zinc-500">
            Período dos indicadores: {{ $period->label() }}, comparado com os {{ $period->days() }} dias anteriores. A projeção é uma estimativa pelo ritmo e pela tendência dos meses fechados, não uma meta.
        </p>
    </div>
</div>
