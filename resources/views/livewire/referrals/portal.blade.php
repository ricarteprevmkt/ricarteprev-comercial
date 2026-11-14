@php($brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'))

<div class="flex flex-col gap-6">
    <x-page-header title="Minhas indicações" subtitle="Indique quem precisa de orientação previdenciária e acompanhe o andamento.">
        <x-slot:actions>
            @if ($indicator && auth()->user()->can('referrals.create'))
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('referral-form:create')">Nova indicação</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (! $indicator)
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.text>Seu acesso ainda não está ligado a um cadastro de indicador. Fale com a Ricarte Prev.</flux:callout.text>
        </flux:callout>
    @else
        @if ($totals)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-stat label="Indicações no ano" :value="$totals['total']" icon="user-group" />
                <x-stat label="Em atendimento" :value="$totals['contacted']" icon="chat-bubble-left-right" />
                <x-stat label="Viraram oportunidade" :value="$totals['opportunities']" icon="sparkles" />
                <x-stat label="Contratos" :value="$totals['signed']" icon="document-check" />
            </div>
        @endif

        <x-card title="Indicações" :padding="false">
            <ul class="divide-y divide-zinc-100">
                @forelse ($referrals as $r)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                        <div>
                            <div class="font-medium text-zinc-900">{{ $r->lead->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $r->referred_at->format('d/m/Y') }}{{ $r->lead->city ? ' · '.$r->lead->city->name : '' }}</div>
                        </div>
                        @if ($r->is_duplicate)
                            <flux:badge size="sm" color="amber">Já era contato da Ricarte</flux:badge>
                        @else
                            <flux:badge size="sm" :color="$r->status->color()">{{ $r->status->label() }}</flux:badge>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-zinc-500">
                        Você ainda não registrou indicações.
                    </li>
                @endforelse
            </ul>
        </x-card>

        @if ($commissions !== null)
            <x-card title="Minhas comissões" :padding="false">
                <ul class="divide-y divide-zinc-100">
                    @forelse ($commissions as $c)
                        <li class="flex items-center justify-between gap-2 px-5 py-3 text-sm">
                            <div>
                                <div class="text-zinc-900">{{ $c->contract->lead->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $c->generated_at->format('d/m/Y') }}{{ $c->paid_total ? ' · pago '.$brl($c->paid_total) : '' }}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="tabular-nums font-medium">{{ $brl($c->approved_amount ?? $c->expected_amount) }}</span>
                                <flux:badge size="sm" :color="$c->status->color()">{{ $c->status->label() }}</flux:badge>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-zinc-500">Nenhuma comissão ainda.</li>
                    @endforelse
                </ul>
            </x-card>
        @endif
    @endif

    <livewire:referrals.referral-form />
</div>
