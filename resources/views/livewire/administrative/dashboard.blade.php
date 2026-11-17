@php
    use App\Metrics\InssDeadlines;
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Administrativo" subtitle="Processos no INSS dos clientes do escritório, lidos do C2 Prev. Só consulta." icon="building-library" />

    <x-adm-tabs />

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <a href="{{ route('adm.processes', ['resultado' => 'in_progress']) }}" wire:navigate>
            <x-stat label="Em andamento" :value="$inProgress" hint="processos no INSS" icon="clock" class="h-full hover:border-brand-300" />
        </a>
        <a href="{{ route('adm.deadlines', ['tipo' => 'exigencia']) }}" wire:navigate>
            <x-stat label="Exigências abertas" :value="$requirements" :hint="$requirementsSoon.' vencem em até 7 dias'" icon="exclamation-circle" :tone="$requirementsSoon > 0 ? 'warning' : null" class="h-full hover:border-brand-300" />
        </a>
        <a href="{{ route('adm.deadlines', ['tipo' => 'agendamento', 'dias' => 7]) }}" wire:navigate>
            <x-stat label="Agendamentos" :value="$appointments" hint="nos próximos 7 dias" icon="calendar-days" class="h-full hover:border-brand-300" />
        </a>
        <a href="{{ route('adm.deadlines', ['tipo' => 'recurso']) }}" wire:navigate>
            <x-stat label="Prazos de recurso" :value="$appeals" hint="indeferidos com prazo correndo" icon="scale" :tone="$appeals > 0 ? 'warning' : null" class="h-full hover:border-brand-300" />
        </a>
        <a href="{{ route('adm.pending') }}" wire:navigate>
            <x-stat label="Sem acompanhamento" :value="$monitoring" hint="o C2 parou de ler (senha do gov.br)" icon="lock-closed" :tone="$monitoring > 0 ? 'danger' : null" class="h-full hover:border-brand-300" />
        </a>
        <x-stat label="Deferimento (30 dias)" :value="$pct($rate)" :hint="$granted.' deferido(s) · '.$denied.' indeferido(s)'" icon="check-badge" />
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <x-card title="Próximos prazos" description="Exigências, agendamentos e recursos dos próximos 14 dias, e exigências vencidas." :padding="false" class="xl:col-span-3">
            <x-slot:actions>
                <flux:button size="sm" variant="ghost" :href="route('adm.deadlines')" wire:navigate icon-trailing="arrow-right">Todos os prazos</flux:button>
            </x-slot:actions>
            @if ($deadlines->isEmpty())
                <x-empty-state icon="calendar" title="Nenhum prazo nos próximos 14 dias" />
            @else
                <ul class="divide-y divide-zinc-100">
                    @foreach ($deadlines as $item)
                        <li class="flex items-start justify-between gap-3 px-5 py-3" wire:key="d-{{ $item['type'] }}-{{ $item['process']->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('adm.processes.show', $item['process']) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $item['process']->client_name ?? 'Cliente sem nome' }}</a>
                                <div class="truncate text-xs text-zinc-500">{{ $item['label'] }} · {{ $item['process']->benefit_type ?? 'benefício não informado' }}{{ $item['detail'] ? ' · '.$item['detail'] : '' }}</div>
                            </div>
                            <div class="shrink-0 text-right">
                                <flux:badge size="sm" :color="InssDeadlines::color($item['days'])">{{ InssDeadlines::when($item['days']) }}</flux:badge>
                                <div class="mt-0.5 text-xs tabular-nums text-zinc-500">{{ $item['date']->format('d/m/Y') }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Por tipo de benefício" description="Taxa = deferidos ÷ (deferidos + indeferidos)." :padding="false" class="xl:col-span-2">
            @if ($byBenefit->isEmpty())
                <x-empty-state icon="document-text" title="Nenhum processo lido do C2" />
            @else
                <flux:table class="px-4">
                    <flux:table.columns>
                        <flux:table.column>Benefício</flux:table.column>
                        <flux:table.column align="end">Andamento</flux:table.column>
                        <flux:table.column align="end">Def.</flux:table.column>
                        <flux:table.column align="end">Indef.</flux:table.column>
                        <flux:table.column align="end">Taxa</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($byBenefit as $row)
                            <flux:table.row :key="$row['label']">
                                <flux:table.cell class="max-w-48 truncate" :title="$row['label']">{{ $row['label'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $row['in_progress'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $row['granted'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $row['denied'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium">{{ $pct($row['rate']) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </x-card>
    </div>

    <p class="text-xs text-zinc-500">
        Última leitura do C2: {{ $lastSync?->format('d/m/Y H:i') ?? 'ainda não' }}. Protocolos antigos de cada cliente ficam fora do escopo e não entram aqui (veja Processos → Fora do escopo).
    </p>
</div>
