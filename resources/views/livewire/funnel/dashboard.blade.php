@php
    use App\Metrics\FunnelMetrics;
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
    $stages = $funnel['stages'];
    $prev = $prevFunnel['stages'];
    $icons = ['user', 'check-badge', 'calendar-days', 'document-text', 'document-check'];
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Funil comercial" subtitle="Fluxo de oportunidades e taxa de conversão da equipe comercial." icon="funnel" :period="true">
        <x-slot:actions>
            @if ($executives->isNotEmpty())
                <flux:select wire:model.live="executiveFilter" size="sm" class="min-w-48" aria-label="Executivo">
                    <flux:select.option value="">Toda a equipe</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-funnel-tabs />

    {{-- Cartões --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($stages as $i => $row)
            <x-stat
                :label="$i === 0 ? 'Total de contatos' : $row['stage']->label()"
                :value="number_format($row['count'], 0, ',', '.')"
                :icon="$icons[$i]"
                :delta="FunnelMetrics::delta($row['count'], $prev[$i]['count'])"
                hint="vs. período anterior" />
        @endforeach
        <x-stat label="Taxa de conversão" :value="$pct($funnel['overall'])" icon="receipt-percent"
            :hint="$funnel['overall'] !== null && $prevFunnel['overall'] !== null ? (($funnel['overall'] - $prevFunnel['overall'] >= 0 ? '+' : '').number_format($funnel['overall'] - $prevFunnel['overall'], 1, ',', '.').' p.p. vs. anterior') : 'contratos ÷ contatos'" />
    </div>

    @if ($funnel['base'] === 0 && $signed === 0)
        <x-card>
            <x-empty-state icon="funnel" title="Nenhuma oportunidade aberta em {{ mb_strtolower($period->label()) }}"
                description="Os números aparecem assim que contatos e oportunidades forem registrados. Mude o período no seletor acima para ver outras datas.">
                @can('leads.manage')
                    <flux:button size="sm" variant="primary" icon="plus" :href="route('funnel.leads')" wire:navigate class="mt-2">Cadastrar contato</flux:button>
                @endcan
            </x-empty-state>
        </x-card>
    @else
        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Funil --}}
            <x-card title="Funil de oportunidades" description="Oportunidades abertas no período e até onde chegaram" class="xl:col-span-2">
                <x-funnel-chart :stages="array_map(fn ($row) => ['label' => $row['stage']->label(), 'count' => $row['count'], 'note' => $pct($row['share'])], $stages)" />
                <p class="mt-3 text-xs text-zinc-500">À direita, a parcela do total de contatos que chegou a cada etapa.</p>
            </x-card>

            {{-- Conversão por etapa --}}
            <x-card title="Conversão entre etapas" description="Quantos avançaram para a etapa seguinte">
                <ul class="flex flex-col gap-4">
                    @foreach (array_slice($stages, 1) as $i => $row)
                        <li>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-600">{{ $stages[$i]['stage']->label() }} → {{ $row['stage']->label() }}</span>
                                <strong class="tabular-nums text-brand-900">{{ $pct($row['conversion']) }}</strong>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-zinc-100" aria-hidden="true">
                                <div class="h-full rounded-full {{ $i === 3 ? 'bg-gold-400' : 'bg-brand-500' }}" style="width: {{ $row['conversion'] ?? 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-5 flex items-center justify-between rounded-lg bg-brand-50 px-4 py-3">
                    <span class="text-sm text-brand-900">Tempo médio até o contrato</span>
                    <strong class="tabular-nums text-brand-900">{{ $avgDays === null ? '—' : number_format($avgDays, 1, ',', '.').' dias' }}</strong>
                </div>
                <p class="mt-2 text-xs text-zinc-500">{{ $signed }} contrato(s) assinado(s) no período{{ $prevSigned ? ', contra '.$prevSigned.' no anterior' : '' }}.</p>
            </x-card>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Por executivo --}}
            <x-card title="Desempenho por executivo" :padding="false" class="xl:col-span-2">
                @if (count($byExecutive) === 0)
                    <p class="px-5 py-8 text-center text-sm text-zinc-500">Sem oportunidades no período.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-left text-xs text-zinc-500">
                                    <th scope="col" class="px-5 py-2.5 font-medium">Executivo</th>
                                    <th scope="col" class="px-3 py-2.5 text-end font-medium">Contatos</th>
                                    <th scope="col" class="px-3 py-2.5 text-end font-medium">Qualificados</th>
                                    <th scope="col" class="px-3 py-2.5 text-end font-medium">Agendados</th>
                                    <th scope="col" class="px-3 py-2.5 text-end font-medium">Propostas</th>
                                    <th scope="col" class="px-3 py-2.5 text-end font-medium">Contratos</th>
                                    <th scope="col" class="px-5 py-2.5 text-end font-medium">Conversão</th>
                                </tr>
                            </thead>
                            <tbody class="tabular-nums">
                                @foreach ($byExecutive as $row)
                                    <tr class="border-b border-zinc-100 last:border-0">
                                        <td class="px-5 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <x-avatar :name="$row['executive']->user->name" size="sm" :src="$row['executive']->photo_path ? route('photos.executive', $row['executive']) : null" />
                                                <span class="font-medium text-zinc-900">{{ $row['executive']->user->name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-end">{{ $row['base'] }}</td>
                                        <td class="px-3 py-2.5 text-end">{{ $row['qualified'] }}</td>
                                        <td class="px-3 py-2.5 text-end">{{ $row['scheduled'] }}</td>
                                        <td class="px-3 py-2.5 text-end">{{ $row['proposals'] }}</td>
                                        <td class="px-3 py-2.5 text-end font-semibold text-brand-900">{{ $row['signed'] }}</td>
                                        <td class="px-5 py-2.5 text-end">{{ $pct($row['conversion']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            {{-- Origem --}}
            <x-card title="Origem das oportunidades" description="De onde vieram os contatos do período">
                <ul class="flex flex-col gap-3">
                    @forelse ($bySource as $row)
                        <li>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-700">{{ $row['source'] }}</span>
                                <span class="tabular-nums"><strong class="text-brand-900">{{ $row['base'] }}</strong> <span class="text-zinc-500">· {{ $pct($row['share']) }}</span></span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-zinc-100" aria-hidden="true">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ $row['share'] ?? 0 }}%"></div>
                            </div>
                            <div class="mt-0.5 text-xs text-zinc-500">{{ $row['signed'] }} contrato(s) · conversão {{ $pct($row['conversion']) }}</div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-zinc-500">Sem oportunidades no período.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-card title="Oportunidades em andamento" :padding="false" class="xl:col-span-2">
                <x-slot:actions>
                    <a href="{{ route('funnel.opportunities') }}" wire:navigate class="text-sm font-medium text-brand-500 hover:underline">Ver todas</a>
                </x-slot:actions>
                <ul class="divide-y divide-zinc-100">
                    @forelse ($inProgress as $o)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                            <div class="min-w-0">
                                <a href="{{ route('funnel.opportunities.show', $o) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $o->lead->name }}</a>
                                <div class="text-xs text-zinc-500">{{ $o->next_action ?? 'Sem próxima ação' }}{{ $o->next_action_due ? ' · até '.$o->next_action_due->format('d/m') : '' }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($o->isOverdue())<flux:badge size="sm" color="red">Prazo vencido</flux:badge>@endif
                                <flux:badge size="sm" :color="$o->stage->color()">{{ $o->stage->label() }}</flux:badge>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-zinc-500">Nenhuma oportunidade em aberto.</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="Motivos de encerramento" description="Oportunidades encerradas sem contrato no período">
                <ul class="flex flex-col gap-3">
                    @forelse ($lossReasons as $row)
                        <li>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-700">{{ $row['reason'] }}</span>
                                <span class="tabular-nums"><strong>{{ $row['count'] }}</strong> <span class="text-zinc-500">· {{ $pct($row['share']) }}</span></span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-zinc-100" aria-hidden="true">
                                <div class="h-full rounded-full bg-gold-400" style="width: {{ $row['share'] ?? 0 }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-zinc-500">Nenhum encerramento no período.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    @endif
</div>
