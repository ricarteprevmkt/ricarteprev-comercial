<div class="flex flex-col gap-6">
    <x-page-header title="Parceiros regionais" subtitle="Indicações recebidas e como cada uma está evoluindo no funil.">
        <x-slot:actions>
            @can('referrals.create')
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('referral-form:create')">Registrar indicação</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-referral-tabs />

    <x-card :padding="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Nome de quem foi indicado" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="statusFilter" class="max-w-52" aria-label="Situação">
                <flux:select.option value="">Todas as situações</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($indicators->isNotEmpty())
                <flux:select wire:model.live="indicatorFilter" class="max-w-52" aria-label="Parceiro">
                    <flux:select.option value="">Todos os parceiros</flux:select.option>
                    @foreach ($indicators as $indicator)
                        <flux:select.option :value="$indicator->id">{{ $indicator->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="regionFilter" class="max-w-44" aria-label="Região">
                    <flux:select.option value="">Todas as regiões</flux:select.option>
                    @foreach ($regions as $region)
                        <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:switch wire:model.live="showDuplicates" label="Ver duplicadas" />
        </div>

        @if ($this->referrals->isEmpty())
            <x-empty-state icon="user-group" :title="$showDuplicates ? 'Nenhuma indicação duplicada' : 'Nenhuma indicação encontrada'"
                description="Registre as indicações recebidas dos moradores e parceiros locais." />
        @else
            <flux:table :paginate="$this->referrals" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Indicado</flux:table.column>
                    <flux:table.column>Situação</flux:table.column>
                    <flux:table.column class="max-md:hidden">Parceiro</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Responsável</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Data</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->referrals as $referral)
                        <flux:table.row :key="$referral->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $referral->lead->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $referral->lead->city?->label() }}{{ $referral->lead->neighborhood ? ' · '.$referral->lead->neighborhood : '' }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$referral->status->color()">{{ $referral->status->label() }}</flux:badge>
                                @if ($referral->is_duplicate)
                                    <div class="mt-0.5 text-xs text-amber-700">duplicada</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden">
                                {{ $referral->indicator->name }}
                                <div class="text-xs text-zinc-500">{{ $referral->channel?->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden">{{ $referral->executive?->user->name ?? 'Sem responsável' }}</flux:table.cell>
                            <flux:table.cell class="max-lg:hidden tabular-nums">{{ $referral->referred_at->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @if (! $referral->is_duplicate && auth()->user()->can('leads.manage') && $referral->status !== \App\Enums\ReferralStatus::Signed)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Ações para {{ $referral->lead->name }}" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="openChange({{ $referral->id }}, 'contacted')">Contato realizado</flux:menu.item>
                                            <flux:menu.item wire:click="openChange({{ $referral->id }}, 'qualified')">Qualificada</flux:menu.item>
                                            <flux:menu.item variant="danger" wire:click="openChange({{ $referral->id }}, 'lost')">Perdida</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <p class="text-xs text-zinc-500">A situação avança sozinha conforme o contato evolui no funil: atendimento agendado vira "Oportunidade gerada", proposta e contrato seguem o mesmo caminho.</p>

    <livewire:referrals.referral-form />

    <flux:modal name="referral-status" class="w-full max-w-md">
        <form wire:submit="saveChange" class="flex flex-col gap-4">
            <flux:heading size="lg">Marcar como {{ \App\Enums\ReferralStatus::tryFrom($change['status'])?->label() }}</flux:heading>
            <flux:textarea wire:model="change.note" :label="$change['status'] === 'lost' ? 'Motivo' : 'Observação (opcional)'" rows="2" />
            @error('change.status') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
