@php
    use App\Metrics\InssDeadlines;
    $p = $process;
    $days = fn ($date) => (int) today()->diffInDays($date, false);
@endphp

<div class="flex flex-col gap-6">
    <x-page-header :title="$p->client_name ?? 'Processo no INSS'" :subtitle="($p->benefit_type ?? 'Benefício não informado').($p->request_number ? ' · protocolo '.$p->request_number : '')" icon="document-text">
        <x-slot:actions>
            <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('adm.processes')" wire:navigate>Processos</flux:button>
            @if ($p->c2Url())
                <flux:button size="sm" variant="primary" icon-trailing="arrow-top-right-on-square" :href="$p->c2Url()" target="_blank" rel="noopener">Abrir no C2</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2">
        <flux:badge :color="$p->outcome->color()">{{ $p->outcome->label() }}</flux:badge>
        @if (! $p->in_scope)
            <flux:badge color="zinc">Fora do escopo (protocolo antigo)</flux:badge>
        @endif
        @if ($p->monitoringLabel())
            <flux:badge color="red" icon="lock-closed">{{ $p->monitoringLabel() }}</flux:badge>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="flex flex-col gap-6 xl:col-span-2">
            @if ($p->requirement_text || $p->requirement_deadline)
                <x-card title="Exigência">
                    <div class="flex flex-col gap-2 text-sm">
                        @if ($p->requirement_deadline)
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-zinc-700">Prazo:</span>
                                <span class="tabular-nums">{{ $p->requirement_deadline->format('d/m/Y') }}</span>
                                @if ($p->outcome === \App\Enums\InssOutcome::InProgress)
                                    <flux:badge size="sm" :color="InssDeadlines::color($days($p->requirement_deadline))">{{ InssDeadlines::when($days($p->requirement_deadline)) }}</flux:badge>
                                @endif
                            </div>
                        @endif
                        @if ($p->requirement_text)
                            <p class="whitespace-pre-line text-zinc-700">{{ $p->requirement_text }}</p>
                        @endif
                    </div>
                </x-card>
            @endif

            @if ($p->appointment_date)
                <x-card title="Agendamento">
                    <p class="text-sm text-zinc-700">
                        <span class="tabular-nums font-medium">{{ $p->appointment_date->format('d/m/Y') }}{{ $p->appointment_time ? ' às '.$p->appointment_time : '' }}</span>
                        @if ($p->appointment_date->gte(today()))
                            <flux:badge size="sm" class="ml-1" :color="InssDeadlines::color($days($p->appointment_date))">{{ InssDeadlines::when($days($p->appointment_date)) }}</flux:badge>
                        @endif
                        @if ($p->appointment_location)<br>{{ $p->appointment_location }}@endif
                    </p>
                </x-card>
            @endif

            @if ($p->decision_text || $p->decision_reason || $p->appeal_deadline)
                <x-card title="Decisão">
                    <dl class="grid gap-2 text-sm sm:grid-cols-[10rem_1fr]">
                        <dt class="font-medium text-zinc-700">Resultado</dt><dd>{{ $p->decision_text ?? '—' }}</dd>
                        @if ($p->decision_reason)
                            <dt class="font-medium text-zinc-700">Motivo</dt><dd class="whitespace-pre-line">{{ $p->decision_reason }}</dd>
                        @endif
                        @if ($p->appeal_deadline)
                            <dt class="font-medium text-zinc-700">Prazo de recurso</dt>
                            <dd class="flex items-center gap-2"><span class="tabular-nums">{{ $p->appeal_deadline->format('d/m/Y') }}</span>
                                <flux:badge size="sm" :color="InssDeadlines::color($days($p->appeal_deadline))">{{ InssDeadlines::when($days($p->appeal_deadline)) }}</flux:badge></dd>
                        @endif
                    </dl>
                </x-card>
            @endif

            <x-card title="Movimentações" description="Consultadas no C2 na hora." :padding="false">
                @php($movements = $this->movements)
                @if ($movements['error'])
                    <p class="px-5 py-6 text-sm text-amber-800">Não foi possível consultar as movimentações: {{ $movements['error'] }}</p>
                @elseif ($movements['items'] === [])
                    <x-empty-state icon="clock" title="Nenhuma movimentação registrada no C2" />
                @else
                    <ol class="divide-y divide-zinc-100">
                        @foreach ($movements['items'] as $i => $m)
                            <li class="flex gap-4 px-5 py-3 text-sm" wire:key="m-{{ $i }}">
                                <span class="w-24 shrink-0 tabular-nums text-zinc-500">{{ $m['date']?->format('d/m/Y') ?? '—' }}</span>
                                <div class="min-w-0">
                                    <p class="font-medium text-zinc-900">{{ $m['title'] ?? $m['kind'] }}</p>
                                    @if ($m['description'])<p class="whitespace-pre-line text-zinc-600">{{ $m['description'] }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>
        </div>

        <div class="flex flex-col gap-6">
            <x-card title="Dados do processo">
                <dl class="grid grid-cols-[8rem_minmax(0,1fr)] gap-x-3 gap-y-2 break-words text-sm">
                    <dt class="text-zinc-500">CPF</dt><dd class="tabular-nums">{{ $p->cpf_last_digits ? '***.***.***-'.$p->cpf_last_digits : 'não informado' }}</dd>
                    <dt class="text-zinc-500">Protocolo</dt><dd class="tabular-nums">{{ $p->request_number ?? '—' }}</dd>
                    <dt class="text-zinc-500">Nº do benefício</dt><dd class="tabular-nums">{{ $p->benefit_number ?? '—' }}</dd>
                    <dt class="text-zinc-500">Benefício</dt><dd>{{ $p->benefit_type ?? '—' }}</dd>
                    @if ($p->service && $p->service !== $p->benefit_type)
                        <dt class="text-zinc-500">Serviço</dt><dd>{{ $p->service }}</dd>
                    @endif
                    <dt class="text-zinc-500">Situação no INSS</dt><dd>{{ $p->status_text ?? '—' }}</dd>
                    <dt class="text-zinc-500">Aberto em</dt><dd class="tabular-nums">{{ $p->opened_at?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="text-zinc-500">Concluído em</dt><dd class="tabular-nums">{{ $p->concluded_at?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="text-zinc-500">Verificado no INSS</dt><dd class="tabular-nums">{{ $p->checked_at?->setTimezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                </dl>
            </x-card>

            <x-card title="No comercial">
                @if (! $p->lead)
                    <p class="text-sm text-zinc-500">O CPF deste cliente não bate com nenhum contato do sistema comercial.</p>
                @else
                    <div class="flex flex-col gap-3 text-sm">
                        <p><span class="text-zinc-500">Contato:</span> <span class="font-medium text-zinc-900">{{ $p->lead->name }}</span></p>
                        @forelse ($this->contracts as $contract)
                            <div class="rounded-lg bg-zinc-50 px-3 py-2">
                                Contrato assinado em <span class="tabular-nums">{{ $contract->signed_at?->format('d/m/Y') }}</span>
                                <div class="text-xs text-zinc-500">Executivo: {{ $contract->executive->user->name }}</div>
                                @can('view', $contract)
                                    <a href="{{ route('funnel.opportunities.show', $contract->opportunity_id) }}" wire:navigate class="text-xs font-medium text-brand-900 hover:underline">Abrir no funil</a>
                                @endcan
                            </div>
                        @empty
                            <p class="text-zinc-500">Sem contrato assinado no sistema.</p>
                        @endforelse
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
