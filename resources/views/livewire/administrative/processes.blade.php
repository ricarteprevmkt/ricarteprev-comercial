<div class="flex flex-col gap-6">
    <x-page-header title="Administrativo" subtitle="Processos no INSS dos clientes do escritório, lidos do C2 Prev. Só consulta." icon="building-library" />

    <x-adm-tabs />

    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-zinc-100 p-4">
            <div class="flex gap-1 text-sm" role="tablist" aria-label="Escopo">
                <button type="button" wire:click="$set('scope', '')" role="tab" aria-selected="{{ $scope === '' ? 'true' : 'false' }}"
                    @class(['rounded-md px-3 py-1.5 font-medium', 'bg-brand-900 text-white' => $scope === '', 'text-zinc-600 hover:bg-zinc-100' => $scope !== ''])>Do escritório</button>
                <button type="button" wire:click="$set('scope', 'fora')" role="tab" aria-selected="{{ $scope === 'fora' ? 'true' : 'false' }}"
                    @class(['rounded-md px-3 py-1.5 font-medium', 'bg-brand-900 text-white' => $scope === 'fora', 'text-zinc-600 hover:bg-zinc-100' => $scope !== 'fora'])>Fora do escopo ({{ $outOfScope }})</button>
            </div>
            @if ($scope === 'fora')
                <p class="text-sm text-zinc-500">Protocolos antigos que o C2 traz da base do INSS: abertos antes da assinatura do contrato (com a folga dos Parâmetros) ou, para clientes sem contrato no sistema, já encerrados e abertos antes do período dos Parâmetros. Não entram no painel, nos prazos nem no resultado do comercial.</p>
            @endif
            <div class="flex flex-wrap gap-3">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cliente, protocolo ou nº do benefício" class="max-w-xs" aria-label="Buscar" />
                <flux:select wire:model.live="outcome" class="max-w-48" aria-label="Resultado">
                    <flux:select.option value="">Todos os resultados</flux:select.option>
                    @foreach ($outcomes as $o)
                        <flux:select.option :value="$o->value">{{ $o->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="benefit" class="max-w-60" aria-label="Benefício">
                    <flux:select.option value="">Todos os benefícios</flux:select.option>
                    @foreach ($benefits as $b)
                        <flux:select.option :value="$b">{{ $b }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="flag" class="max-w-60" aria-label="Situação">
                    <flux:select.option value="">Todas as situações</flux:select.option>
                    @foreach (\App\Livewire\Administrative\Processes::FLAGS as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($this->processes->isEmpty())
            <x-empty-state icon="document-text" title="Nenhum processo encontrado" description="Os processos são lidos do C2 Prev a cada 5 minutos." />
        @else
            <flux:table :paginate="$this->processes" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Cliente</flux:table.column>
                    <flux:table.column class="max-md:hidden">Benefício</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Situação no INSS</flux:table.column>
                    <flux:table.column>Resultado</flux:table.column>
                    <flux:table.column class="max-sm:hidden">Aberto em</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->processes as $process)
                        <flux:table.row :key="$process->id">
                            <flux:table.cell>
                                <a href="{{ route('adm.processes.show', $process) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $process->client_name ?? 'Cliente sem nome' }}</a>
                                <div class="flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                                    <span>{{ $process->request_number ? 'Protocolo '.$process->request_number : 'sem protocolo' }}</span>
                                    @if ($process->requirement_deadline || $process->requirement_text)
                                        @if ($process->outcome === \App\Enums\InssOutcome::InProgress)<span class="font-medium text-amber-700">exigência</span>@endif
                                    @endif
                                    @if ($process->appointment_date?->gte(today()))<span class="font-medium text-sky-700">agendamento {{ $process->appointment_date->format('d/m') }}</span>@endif
                                    @if ($process->monitoringLabel())<span class="font-medium text-red-700">{{ $process->monitoringLabel() }}</span>@endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden">{{ $process->benefit_type ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="max-lg:hidden max-w-60 truncate text-zinc-600" :title="$process->status_text">{{ $process->status_text ?? '—' }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$process->outcome->color()">{{ $process->outcome->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="max-sm:hidden tabular-nums">{{ $process->opened_at?->format('d/m/Y') ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>
</div>
