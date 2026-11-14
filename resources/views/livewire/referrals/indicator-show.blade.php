@php
    $i = $indicator;
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.').'%';
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp

<div class="flex flex-col gap-6">
    <x-page-header :title="$i->name" :subtitle="trim(($i->city?->label() ?? '').($i->neighborhood ? ' · '.$i->neighborhood : '').($i->region ? ' · '.$i->region->name : ''), ' ·')" :period="true" />

    <x-referral-tabs />

    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
        <x-card>
            <div class="flex flex-col items-center gap-3 text-center">
                <x-avatar :name="$i->name" size="lg" :src="$i->photo_path ? route('photos.indicator', $i) : null" />
                <flux:badge size="sm" :color="$i->status->color()">{{ $i->status->label() }}</flux:badge>
            </div>
            <dl class="mt-5 flex flex-col gap-3 text-sm">
                <div><dt class="text-zinc-500">WhatsApp</dt><dd><a href="{{ \App\Support\Phone::whatsappUrl($i->whatsapp) }}" target="_blank" rel="noopener" class="tabular-nums text-success-500 hover:underline">{{ \App\Support\Phone::format($i->whatsapp) }}</a></dd></div>
                <div><dt class="text-zinc-500">Tipo</dt><dd class="text-zinc-900">{{ $i->referralChannel?->name ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Executivo que atende</dt><dd class="text-zinc-900">{{ $i->defaultExecutive?->user->name ?? 'Definido a cada indicação' }}</dd></div>
                <div><dt class="text-zinc-500">Regra de comissão</dt><dd class="text-zinc-900">{{ $i->commissionRule ? $i->commissionRule->name.' ('.$i->commissionRule->summary().')' : 'Sem comissão' }}</dd></div>
                <div><dt class="text-zinc-500">Cadastrado em</dt><dd class="text-zinc-900">{{ $i->registered_at->format('d/m/Y') }}</dd></div>
            </dl>
        </x-card>

        <div class="flex flex-col gap-6">
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-stat label="Indicações" :value="$totals['total']" icon="user-group" :hint="$period->label()" />
                <x-stat label="Qualificadas" :value="$totals['qualified']" icon="check-badge" :hint="$totals['opportunities'].' viraram oportunidade'" />
                <x-stat label="Contratos" :value="$contracts" icon="document-check" :hint="'conversão '.$pct($totals['conversion'])" />
                <x-stat label="Comissões pagas" :value="$brl($commissions['paid'])" icon="banknotes" :hint="'previstas '.$brl($commissions['expected'])" />
            </div>

            <x-card title="Indicações" description="Últimas 30" :padding="false">
                <ul class="divide-y divide-zinc-100">
                    @forelse ($referrals as $r)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 text-sm">
                            <div>
                                <div class="font-medium text-zinc-900">{{ $r->lead->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $r->referred_at->format('d/m/Y') }} · {{ $r->lead->city?->name }} · {{ $r->executive?->user->name ?? 'sem responsável' }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($r->is_duplicate)<span class="text-xs text-amber-700">duplicada</span>@endif
                                <flux:badge size="sm" :color="$r->status->color()">{{ $r->status->label() }}</flux:badge>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-zinc-500">Nenhuma indicação registrada.</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card title="Comissões" :padding="false">
                <ul class="divide-y divide-zinc-100">
                    @forelse ($commissionList as $c)
                        <li class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm">
                            <div>
                                <div class="text-zinc-900">{{ $c->contract->lead->name }}</div>
                                <div class="text-xs text-zinc-500">gerada em {{ $c->generated_at->format('d/m/Y') }}{{ $c->paid_total ? ' · pago '.$brl($c->paid_total) : '' }}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="tabular-nums font-medium">{{ $brl($c->approved_amount ?? $c->expected_amount) }}</span>
                                <flux:badge size="sm" :color="$c->status->color()">{{ $c->status->label() }}</flux:badge>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-zinc-500">Nenhuma comissão gerada.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</div>
