@php($brl = fn ($v) => \App\Support\Money::format($v))

<div class="flex flex-col gap-6">
    <x-page-header title="Custos e resultados" subtitle="Despesas lançadas, comprovantes e aprovação." :period="true">
        <x-slot:actions>
            @can('expenses.manage')
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('expense-form:create')">Lançar despesa</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-finance-tabs />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat label="Aguardando aprovação" :value="$brl($this->totals['pending'])" icon="clock" :hint="$periodLabel" :tone="(float) $this->totals['pending'] > 0 ? 'warning' : null" />
        <x-stat label="Aprovadas" :value="$brl($this->totals['approved'])" icon="check-circle" :hint="$periodLabel" />
        <x-stat label="Recusadas" :value="$brl($this->totals['rejected'])" icon="x-circle" :hint="$periodLabel" />
    </div>

    <x-card :padding="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-zinc-100 p-4">
            <flux:select wire:model.live="statusFilter" class="max-w-52" aria-label="Situação">
                <flux:select.option value="">Todas as situações</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="categoryFilter" class="max-w-56" aria-label="Categoria">
                <flux:select.option value="">Todas as categorias</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($executives->isNotEmpty())
                <flux:select wire:model.live="executiveFilter" class="max-w-52" aria-label="Executivo">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="geral">Despesas gerais</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @if ($canApprove && count($selected) > 0)
                <flux:button size="sm" variant="primary" icon="check" wire:click="approveSelected">Aprovar {{ count($selected) }} selecionada(s)</flux:button>
            @endif
        </div>

        @if ($this->expenses->isEmpty())
            <x-empty-state icon="receipt-percent" title="Nenhuma despesa no período" description="Lance despesas pela agenda ou pelo botão acima." />
        @else
            <flux:table :paginate="$this->expenses" class="px-4">
                <flux:table.columns>
                    @if ($canApprove)<flux:table.column class="w-10"><span class="sr-only">Selecionar</span></flux:table.column>@endif
                    <flux:table.column>Despesa</flux:table.column>
                    <flux:table.column class="max-md:hidden">Executivo</flux:table.column>
                    <flux:table.column align="end">Valor</flux:table.column>
                    <flux:table.column>Situação</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->expenses as $expense)
                        @php($mayApprove = $canApprove && $expense->status === \App\Enums\ExpenseStatus::Pending && auth()->user()->can('approve', $expense))
                        <flux:table.row :key="$expense->id">
                            @if ($canApprove)
                                <flux:table.cell>
                                    @if ($mayApprove)
                                        <input type="checkbox" value="{{ $expense->id }}" wire:model.live="selected" id="exp-{{ $expense->id }}" class="size-4 accent-brand-900" aria-label="Selecionar {{ $expense->description }}">
                                    @endif
                                </flux:table.cell>
                            @endif
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $expense->category->name }} · {{ $expense->description }}</div>
                                <div class="flex flex-wrap gap-x-3 text-xs text-zinc-500">
                                    <span>{{ $expense->spent_on->format('d/m/Y') }}</span>
                                    @if ($expense->visit)<span>visita: {{ $expense->visit->displayName() }}</span>@endif
                                    @foreach ($expense->attachments as $file)
                                        <a href="{{ route('attachments.show', $file) }}" target="_blank" class="text-brand-500 hover:underline">comprovante</a>
                                    @endforeach
                                </div>
                                @if ($expense->rejection_reason)
                                    <div class="text-xs text-red-700">Motivo: {{ $expense->rejection_reason }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden">{{ $expense->executive?->user->name ?? 'Geral' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums font-medium">{{ $brl($expense->amount) }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$expense->status->color()">{{ $expense->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($mayApprove)
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $expense->id }})" aria-label="Aprovar">Aprovar</flux:button>
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="openReject({{ $expense->id }})" aria-label="Recusar" />
                                    </div>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <livewire:agenda.expense-form />

    <flux:modal name="expense-reject" class="w-full max-w-md">
        <form wire:submit="reject" class="flex flex-col gap-4">
            <flux:heading size="lg">Recusar despesa</flux:heading>
            <flux:textarea wire:model="rejectionReason" label="Motivo" rows="2" required description="O executivo verá este motivo." />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Recusar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
