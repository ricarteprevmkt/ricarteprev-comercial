@php($brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'))

<div class="flex flex-col gap-6">
    <x-page-header title="Funil comercial" subtitle="Contratos registrados, valor contratado e receita efetivamente recebida." :period="true" />

    <x-funnel-tabs />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Contratos assinados" :value="$this->totals['signed']" :hint="$periodLabel" icon="document-check" />
        <x-stat label="Valor contratado" :value="$brl($this->totals['contracted'])" :hint="$periodLabel" icon="banknotes" />
        <x-stat label="Receita recebida" :value="$brl($this->totals['received'])" :hint="$periodLabel" icon="arrow-down-tray" />
        <x-stat label="Aguardando conclusão" :value="$this->totals['pending']" hint="todos os períodos" icon="clock" :tone="$this->totals['pending'] > 0 ? 'warning' : null" />
    </div>

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cliente ou número" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="statusFilter" class="max-w-52" aria-label="Situação">
                <flux:select.option value="">Todas as situações</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($c2On)
                <flux:select wire:model.live="inssFilter" class="max-w-60" aria-label="Resultado no INSS">
                    <flux:select.option value="">Todos os resultados no INSS</flux:select.option>
                    @foreach ($outcomes as $outcome)
                        <flux:select.option :value="$outcome->value">{{ $outcome->label() }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="sem-c2">Sem cadastro no C2 há mais de {{ $c2Days }} dia(s)</flux:select.option>
                </flux:select>
            @endif
        </div>

        @if ($this->contracts->isEmpty())
            <x-empty-state icon="document-check" title="Nenhum contrato registrado" description="Contratos são registrados a partir da oportunidade." />
        @else
            <flux:table :paginate="$this->contracts" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Cliente</flux:table.column>
                    <flux:table.column>Situação</flux:table.column>
                    @if ($c2On)<flux:table.column class="max-md:hidden">INSS</flux:table.column>@endif
                    <flux:table.column align="end">Contratado</flux:table.column>
                    <flux:table.column align="end" class="max-md:hidden">Recebido</flux:table.column>
                    <flux:table.column class="max-xl:hidden">Origem</flux:table.column>
                    <flux:table.column class="max-xl:hidden">Responsável</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->contracts as $contract)
                        <flux:table.row :key="$contract->id">
                            <flux:table.cell>
                                <a href="{{ route('funnel.opportunities.show', $contract->opportunity_id) }}" wire:navigate class="font-medium text-brand-900 hover:underline">{{ $contract->lead->name }}</a>
                                <div class="text-xs text-zinc-500">{{ $contract->number ? 'Nº '.$contract->number.' · ' : '' }}{{ $contract->signed_at ? 'assinado em '.$contract->signed_at->format('d/m/Y') : 'sem data de assinatura' }}</div>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$contract->status->color()">{{ $contract->status->label() }}</flux:badge></flux:table.cell>
                            @if ($c2On)
                                <flux:table.cell class="max-md:hidden">
                                    @php($outcome = \App\Models\C2Process::overallOutcome($contract->c2Processes))
                                    @if ($outcome)
                                        <flux:badge size="sm" :color="$outcome->color()">{{ $outcome->label() }}</flux:badge>
                                    @elseif ($contract->status === \App\Enums\ContractStatus::Signed && $contract->signed_at?->lte(now()->subDays($c2Days)))
                                        <flux:badge size="sm" color="amber">Sem cadastro no C2</flux:badge>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                            @endif
                            <flux:table.cell align="end" class="tabular-nums">{{ $brl($contract->contract_value) }}</flux:table.cell>
                            <flux:table.cell align="end" class="max-md:hidden tabular-nums text-zinc-600">{{ $contract->received_total ? $brl($contract->received_total) : '—' }}</flux:table.cell>
                            <flux:table.cell class="max-xl:hidden">
                                {{ $contract->source?->name ?? '—' }}
                                @if ($contract->indicator)<div class="text-xs text-zinc-500">por {{ $contract->indicator->name }}</div>@endif
                            </flux:table.cell>
                            <flux:table.cell class="max-xl:hidden">{{ $contract->executive->user->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Ações do contrato de {{ $contract->lead->name }}" />
                                    <flux:menu>
                                        @if ($contract->status === \App\Enums\ContractStatus::Pending)
                                            @can('update', $contract)
                                                <flux:menu.item icon="check-circle" wire:click="openSign({{ $contract->id }})">Marcar como assinado</flux:menu.item>
                                            @endcan
                                        @endif
                                        <flux:menu.item icon="banknotes" wire:click="openReceipts({{ $contract->id }})">Recebimentos</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <flux:modal name="sign-contract" class="w-full max-w-sm">
        <form wire:submit="sign" class="flex flex-col gap-4">
            <flux:heading size="lg">Marcar como assinado</flux:heading>
            <flux:input wire:model="signedAt" type="date" label="Data de assinatura" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Confirmar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="receipts" class="w-full max-w-lg">
        <div class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">Recebimentos</flux:heading>
                <flux:text class="mt-1">{{ $this->selected?->lead->name }} · contratado {{ $this->selected ? $brl($this->selected->contract_value) : '' }}</flux:text>
            </div>

            @if ($this->selected && $this->selected->status === \App\Enums\ContractStatus::Signed && auth()->user()->can('update', $this->selected))
                <form wire:submit="saveReceipt" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <flux:input wire:model="receipt.received_at" type="date" label="Data" />
                    <flux:input wire:model="receipt.amount" type="number" min="0" step="0.01" label="Valor (R$)" />
                    <flux:button type="submit" variant="primary">Lançar</flux:button>
                    <flux:input wire:model="receipt.notes" label="Observação (opcional)" class="sm:col-span-3" />
                </form>
            @elseif ($this->selected && $this->selected->status !== \App\Enums\ContractStatus::Signed)
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Recebimentos podem ser lançados depois que o contrato for assinado.</p>
            @endif

            @if ($this->selected?->receipts->isNotEmpty())
                <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 text-sm">
                    @foreach ($this->selected->receipts as $item)
                        <li class="flex justify-between gap-3 px-4 py-2.5" wire:key="r-{{ $item->id }}">
                            <span>{{ $item->received_at->format('d/m/Y') }}{{ $item->notes ? ' · '.$item->notes : '' }}</span>
                            <span class="tabular-nums font-medium">{{ $brl($item->amount) }}</span>
                        </li>
                    @endforeach
                    <li class="flex justify-between bg-zinc-50 px-4 py-2.5 font-semibold">
                        <span>Total recebido</span>
                        <span class="tabular-nums">{{ $brl($this->selected->receipts->sum('amount')) }}</span>
                    </li>
                </ul>
            @else
                <p class="rounded-lg bg-zinc-50 px-4 py-6 text-center text-sm text-zinc-500">Nenhum recebimento lançado.</p>
            @endif
        </div>
    </flux:modal>
</div>
