@php use App\Metrics\InssDeadlines; @endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Administrativo" subtitle="Processos no INSS dos clientes do escritório, lidos do C2 Prev. Só consulta." icon="building-library" />

    <x-adm-tabs />

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:select wire:model.live="type" class="max-w-52" aria-label="Tipo de prazo">
                <flux:select.option value="">Todos os prazos</flux:select.option>
                @foreach ($types as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="window" class="max-w-52" aria-label="Período">
                @foreach (\App\Livewire\Administrative\Deadlines::WINDOWS as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                <flux:badge size="sm" color="red">vencido</flux:badge>
                <flux:badge size="sm" color="orange">até 3 dias</flux:badge>
                <flux:badge size="sm" color="amber">até 7 dias</flux:badge>
            </div>
        </div>

        @if ($items->isEmpty())
            <x-empty-state icon="calendar" title="Nenhum prazo no período" description="Os prazos vêm do C2: exigência, agendamento e prazo de recurso de cada processo." />
        @else
            <flux:table class="px-4">
                <flux:table.columns>
                    <flux:table.column>Data</flux:table.column>
                    <flux:table.column>Cliente</flux:table.column>
                    <flux:table.column>Prazo</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Detalhe</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($items as $item)
                        <flux:table.row :key="$item['type'].'-'.$item['process']->id">
                            <flux:table.cell>
                                <div class="tabular-nums font-medium">{{ $item['date']->format('d/m/Y') }}</div>
                                <flux:badge size="sm" :color="InssDeadlines::color($item['days'])">{{ InssDeadlines::when($item['days']) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <a href="{{ route('adm.processes.show', $item['process']) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $item['process']->client_name ?? 'Cliente sem nome' }}</a>
                                <div class="text-xs text-zinc-500">{{ $item['process']->benefit_type ?? 'benefício não informado' }}{{ $item['process']->request_number ? ' · protocolo '.$item['process']->request_number : '' }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $item['label'] }}</flux:table.cell>
                            <flux:table.cell class="max-lg:hidden max-w-96 truncate text-zinc-600" :title="$item['detail']">{{ $item['detail'] ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>
</div>
