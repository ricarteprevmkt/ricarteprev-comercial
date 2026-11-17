<div class="flex flex-col gap-6">
    <x-page-header title="Integração C2 Prev" subtitle="Resultado dos processos no INSS, lido do C2 a cada 5 minutos e ligado aos contatos pelo CPF." />

    @if (! $enabled)
        <x-card>
            <div class="flex flex-col gap-2 text-sm text-zinc-700">
                <p class="font-medium text-zinc-900">A integração está desligada.</p>
                <p>Ela é ligada quando a chave da API do C2 é colocada no arquivo <code>.env</code> do servidor (<code>C2_API_KEY</code>). O passo a passo está em <code>docs/configuracao-hostinger.md</code>, Etapa 10.</p>
            </div>
        </x-card>
    @else
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat label="Última leitura" :value="$lastSync?->format('d/m H:i') ?? 'Ainda não'" :hint="$backfillDone ? 'carga inicial concluída' : 'carga inicial em andamento'" icon="arrow-path" :tone="$lastError ? 'warning' : null" />
            <x-stat label="Processos lidos" :value="$total" hint="no C2" icon="document-text" />
            <x-stat label="Ligados a contatos" :value="$linked" hint="pelo CPF" icon="link" />
            <x-stat label="Sem contato" :value="$total - $linked" hint="ver lista abaixo" icon="exclamation-triangle" :tone="$total - $linked > 0 ? 'warning' : null" />
        </div>

        @if ($lastError)
            <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Última falha ({{ \Carbon\CarbonImmutable::parse($lastError['at'])->setTimezone(config('app.timezone'))->format('d/m H:i') }}): {{ $lastError['message'] }}
                A próxima leitura tenta de novo automaticamente.
            </p>
        @endif

        <div class="flex flex-wrap gap-2">
            @foreach ($counts as $row)
                <flux:badge :color="$row['outcome']->color()">{{ $row['outcome']->label() }}: {{ $row['total'] }}</flux:badge>
            @endforeach
        </div>

        <x-card title="Clientes do C2 sem contato no sistema" description="O CPF do processo não bate com nenhum contato. Confira o CPF no cadastro do contato: depois de corrigido, a ligação é feita na próxima leitura." :padding="false">
            @if ($this->unmatched->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Todos os processos estão ligados a contatos.</p>
            @else
                <flux:table :paginate="$this->unmatched" class="px-4">
                    <flux:table.columns>
                        <flux:table.column>Cliente no C2</flux:table.column>
                        <flux:table.column>CPF</flux:table.column>
                        <flux:table.column class="max-md:hidden">Benefício</flux:table.column>
                        <flux:table.column>Resultado</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->unmatched as $process)
                            <flux:table.row :key="$process->id">
                                <flux:table.cell class="font-medium text-zinc-900">{{ $process->client_name ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $process->cpf_last_digits ? '***.***.***-'.$process->cpf_last_digits : 'não informado no C2' }}</flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $process->benefit_type ?? '—' }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" :color="$process->outcome->color()">{{ $process->outcome->label() }}</flux:badge></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </x-card>
    @endif
</div>
