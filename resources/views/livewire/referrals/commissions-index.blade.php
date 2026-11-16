@php($brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'))

<div class="flex flex-col gap-6">
    <x-page-header title="Parceiros regionais" subtitle="Comissões geradas pelas regras de cada parceiro: previstas, aprovadas e pagas." />

    <x-referral-tabs />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Previstas" :value="$brl($this->totals['expected'])" icon="clock" hint="aguardando aprovação" />
        <x-stat label="Aprovadas" :value="$brl($this->totals['approved'])" icon="check-badge" hint="a pagar" />
        <x-stat label="Pagas" :value="$brl($this->totals['paid'])" icon="banknotes" hint="quitadas" />
        <x-stat label="Canceladas" :value="$brl($this->totals['cancelled'])" icon="x-circle" />
    </div>

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:select wire:model.live="statusFilter" class="max-w-44" aria-label="Situação">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="indicatorFilter" class="max-w-56" aria-label="Parceiro">
                <flux:select.option value="">Todos os parceiros</flux:select.option>
                @foreach ($indicators as $indicator)
                    <flux:select.option :value="$indicator->id">{{ $indicator->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->commissions->isEmpty())
            <x-empty-state icon="banknotes" title="Nenhuma comissão"
                description="As comissões são geradas automaticamente quando um contrato de cliente indicado é assinado ou recebido, conforme a regra do parceiro." />
        @else
            <flux:table :paginate="$this->commissions" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Parceiro</flux:table.column>
                    <flux:table.column class="max-md:hidden">Contrato</flux:table.column>
                    <flux:table.column>Situação</flux:table.column>
                    <flux:table.column align="end">Valor</flux:table.column>
                    <flux:table.column align="end" class="max-md:hidden">Pago</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->commissions as $c)
                        <flux:table.row :key="$c->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $c->indicator->name }}</div>
                                <div class="max-w-64 truncate text-xs text-zinc-500" title="{{ $c->rule_snapshot }}">{{ $c->rule_snapshot }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden">
                                {{ $c->contract->lead->name }}
                                <div class="text-xs text-zinc-500">{{ $c->contract->number ? 'Nº '.$c->contract->number.' · ' : '' }}gerada em {{ $c->generated_at->format('d/m/Y') }}</div>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$c->status->color()">{{ $c->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ $brl($c->approved_amount ?? $c->expected_amount) }}
                                @if ($c->approved_amount && $c->approved_amount !== $c->expected_amount)
                                    <div class="text-xs text-zinc-500">previsto {{ $brl($c->expected_amount) }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="max-md:hidden tabular-nums text-zinc-600">{{ $c->paid_total ? $brl($c->paid_total) : '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($c->status === \App\Enums\CommissionStatus::Expected)
                                    <flux:button size="sm" variant="primary" wire:click="open('approve', {{ $c->id }})">Aprovar</flux:button>
                                @elseif ($c->status === \App\Enums\CommissionStatus::Approved)
                                    <flux:button size="sm" variant="primary" wire:click="open('payment', {{ $c->id }})">Pagar</flux:button>
                                @endif
                                @if (in_array($c->status, [\App\Enums\CommissionStatus::Expected, \App\Enums\CommissionStatus::Approved], true) && ! $c->paid_total)
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="open('cancel', {{ $c->id }})" aria-label="Cancelar comissão" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <flux:modal name="commission-approve" class="w-full max-w-md">
        <form wire:submit="saveApprove" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Aprovar comissão</flux:heading>
                <flux:text class="mt-1">{{ $current?->indicator->name }} · previsto {{ $current ? $brl($current->expected_amount) : '' }}</flux:text>
            </div>
            <flux:input wire:model="approve.amount" type="number" min="0" step="0.01" label="Valor aprovado (R$)" required />
            <flux:textarea wire:model="approve.note" label="Observação (opcional)" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Aprovar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="commission-payment" class="w-full max-w-md">
        <form wire:submit="savePayment" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:heading size="lg">Registrar pagamento</flux:heading>
                <flux:text class="mt-1">{{ $current?->indicator->name }} · aprovado {{ $current?->approved_amount ? $brl($current->approved_amount) : '' }}</flux:text>
            </div>
            <flux:input wire:model="payment.amount" type="number" min="0" step="0.01" label="Valor pago (R$)" required />
            <flux:input wire:model="payment.paid_at" type="date" label="Data" required />
            <flux:textarea wire:model="payment.note" label="Observação (ex.: PIX)" rows="2" class="sm:col-span-2" />
            @if ($current?->payments->isNotEmpty())
                <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 text-sm sm:col-span-2">
                    @foreach ($current->payments as $p)
                        <li class="flex justify-between px-3 py-2"><span>{{ $p->paid_at->format('d/m/Y') }}</span><span class="tabular-nums">{{ $brl($p->amount) }}</span></li>
                    @endforeach
                </ul>
            @endif
            <div class="flex justify-end gap-2 sm:col-span-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Registrar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="commission-cancel" class="w-full max-w-md">
        <form wire:submit="saveCancel" class="flex flex-col gap-4">
            <flux:heading size="lg">Cancelar comissão</flux:heading>
            <flux:textarea wire:model="cancel.reason" label="Motivo" rows="2" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Voltar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Cancelar comissão</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
