<div class="flex flex-col gap-6">
    <x-page-header title="Administrativo" subtitle="Processos no INSS dos clientes do escritório, lidos do C2 Prev. Só consulta." icon="building-library" />

    <x-adm-tabs />

    <x-card title="Clientes que o C2 parou de acompanhar" description="Normalmente o cliente trocou a senha do gov.br. Peça a senha nova e atualize no C2." :padding="false">
        @if ($monitoring->isEmpty())
            <p class="px-5 py-6 text-sm text-zinc-500">Todos os processos estão sendo acompanhados.</p>
        @else
            <ul class="divide-y divide-zinc-100">
                @foreach ($monitoring as $p)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm" wire:key="mon-{{ $p->id }}">
                        <div>
                            <a href="{{ route('adm.processes.show', $p) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $p->client_name ?? 'Cliente sem nome' }}</a>
                            <div class="text-xs text-zinc-500">{{ $p->benefit_type ?? 'benefício não informado' }}</div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <flux:badge size="sm" color="red">{{ $p->monitoringLabel() }}</flux:badge>
                            @if ($p->c2Url())
                                <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square" :href="$p->c2Url()" target="_blank" rel="noopener" aria-label="Abrir {{ $p->client_name }} no C2" />
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card title="Exigências vencidas" description="Processos em andamento com prazo de exigência já vencido no C2." :padding="false">
        @if ($overdue->isEmpty())
            <p class="px-5 py-6 text-sm text-zinc-500">Nenhuma exigência vencida.</p>
        @else
            <ul class="divide-y divide-zinc-100">
                @foreach ($overdue as $p)
                    <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm" wire:key="ov-{{ $p->id }}">
                        <div class="min-w-0">
                            <a href="{{ route('adm.processes.show', $p) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $p->client_name ?? 'Cliente sem nome' }}</a>
                            <div class="truncate text-xs text-zinc-500">{{ $p->requirement_text ?? 'exigência sem descrição' }}</div>
                        </div>
                        <span class="shrink-0 tabular-nums text-red-700">venceu em {{ $p->requirement_deadline?->format('d/m/Y') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card title="Contratos assinados sem cadastro no C2" :description="'Assinados há mais de '.$c2Days.' dia(s) e sem processo novo no C2 com o CPF do cliente.'" :padding="false">
        @if ($missing->isEmpty())
            <p class="px-5 py-6 text-sm text-zinc-500">Nenhum contrato aguardando cadastro no C2.</p>
        @else
            <ul class="divide-y divide-zinc-100">
                @foreach ($missing as $contract)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm" wire:key="miss-{{ $contract->id }}">
                        <div>
                            <span class="font-medium text-zinc-900">{{ $contract->lead->name }}</span>
                            <div class="text-xs text-zinc-500">Executivo: {{ $contract->executive->user->name }}</div>
                        </div>
                        <span class="shrink-0 tabular-nums text-zinc-600">assinado em {{ $contract->signed_at?->format('d/m/Y') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($unmatched > 0)
        <p class="text-sm text-zinc-500">
            {{ $unmatched }} processo(s) do C2 não estão ligados a nenhum contato do comercial (CPF diferente ou ausente).
            @can('settings.manage')
                <a href="{{ route('admin.c2') }}" wire:navigate class="font-medium text-brand-900 hover:underline">Ver a lista</a>
            @endcan
        </p>
    @endif
</div>
