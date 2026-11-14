@php
    $o = $opportunity;
    $lead = $o->lead;
    $closed = $o->stage->isClosed();
@endphp

<div class="flex flex-col gap-6">
    <x-page-header :title="$lead->name" :subtitle="'Oportunidade #'.$o->id.' · aberta em '.$o->created_at->format('d/m/Y').' · '.$o->executive->user->name" />

    <x-funnel-tabs />

    {{-- Etapas do funil --}}
    <x-card :padding="false">
        <ol class="grid grid-cols-5 text-center text-[10px] sm:text-sm" aria-label="Etapas do funil">
            @foreach ($funnel as $stage)
                @php
                    $reached = $o->furthest_stage_rank >= $stage->rank();
                    $current = $o->stage === $stage;
                @endphp
                <li @class([
                    'flex min-w-0 flex-col items-center gap-1 border-b-4 px-0.5 py-3 break-words hyphens-auto sm:px-1',
                    'border-gold-400 bg-gold-100/50 font-semibold text-brand-900' => $current,
                    'border-brand-500 text-brand-900' => $reached && ! $current,
                    'border-zinc-200 text-zinc-400' => ! $reached,
                ]) @if ($current) aria-current="step" @endif>
                    <span class="flex size-6 items-center justify-center rounded-full text-[11px] {{ $reached ? 'bg-brand-900 text-white' : 'bg-zinc-200 text-zinc-500' }}">{{ $stage->rank() }}</span>
                    <span class="leading-tight">{{ $stage->label() }}</span>
                </li>
            @endforeach
        </ol>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 px-5 py-3">
            <div class="text-sm">
                Situação atual: <flux:badge size="sm" :color="$o->stage->color()">{{ $o->stage->label() }}</flux:badge>
                @if ($o->lossReason)
                    <span class="ms-1 text-zinc-600">· {{ $o->lossReason->name }}</span>
                @endif
            </div>
            @if ($canUpdate && $o->stage !== \App\Enums\OpportunityStage::Signed)
                <div class="flex flex-wrap gap-2">
                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" icon:trailing="chevron-down">Mover etapa</flux:button>
                        <flux:menu>
                            @foreach ($movable as $stage)
                                @unless ($stage === $o->stage)
                                    <flux:menu.item wire:click="openMove('{{ $stage->value }}')">{{ $stage->label() }}</flux:menu.item>
                                @endunless
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                    @unless ($closed)
                        <flux:modal.trigger name="proposal-form">
                            <flux:button size="sm" icon="document-text">Registrar proposta</flux:button>
                        </flux:modal.trigger>
                    @endunless
                    @if ($canContract && ! $o->contract && ! $closed)
                        <flux:modal.trigger name="contract-form">
                            <flux:button size="sm" variant="primary" icon="document-check">Registrar contrato</flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            @endif
        </div>
    </x-card>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            {{-- Próxima ação --}}
            <x-card title="Próxima ação e negociação">
                @if ($canUpdate && ! $closed)
                    <form wire:submit="saveDetails" class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="details.next_action" label="Próxima ação" placeholder="Ex.: retornar com documentos" class="sm:col-span-2" />
                        <flux:input wire:model="details.next_action_due" type="date" label="Prazo" />
                        <flux:input wire:model="details.estimated_value" type="number" min="0" step="0.01" label="Valor estimado (R$)" />
                        <flux:input wire:model="details.interest" label="Interesse" class="sm:col-span-2" />
                        <div class="flex justify-end sm:col-span-2">
                            <flux:button type="submit" size="sm" variant="primary">Salvar</flux:button>
                        </div>
                    </form>
                @else
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-zinc-500">Próxima ação</dt><dd class="text-zinc-900">{{ $o->next_action ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Prazo</dt><dd class="text-zinc-900">{{ $o->next_action_due?->format('d/m/Y') ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Valor estimado</dt><dd class="tabular-nums text-zinc-900">{{ $o->estimated_value ? 'R$ '.number_format((float) $o->estimated_value, 2, ',', '.') : '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Interesse</dt><dd class="text-zinc-900">{{ $o->interest ?? '—' }}</dd></div>
                    </dl>
                @endif
                @if ($o->isOverdue())
                    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">Prazo vencido em {{ $o->next_action_due->format('d/m/Y') }}.</p>
                @endif
            </x-card>

            {{-- Contrato --}}
            @if ($o->contract)
                <x-card title="Contrato">
                    <dl class="grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-zinc-500">Situação</dt><dd><flux:badge size="sm" :color="$o->contract->status->color()">{{ $o->contract->status->label() }}</flux:badge></dd></div>
                        <div><dt class="text-zinc-500">Número</dt><dd class="text-zinc-900">{{ $o->contract->number ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Assinatura</dt><dd class="text-zinc-900">{{ $o->contract->signed_at?->format('d/m/Y') ?? '—' }}</dd></div>
                        <div><dt class="text-zinc-500">Valor contratado</dt><dd class="tabular-nums text-zinc-900">R$ {{ number_format((float) $o->contract->contract_value, 2, ',', '.') }}</dd></div>
                        <div><dt class="text-zinc-500">Receita prevista</dt><dd class="tabular-nums text-zinc-900">{{ $o->contract->expected_revenue ? 'R$ '.number_format((float) $o->contract->expected_revenue, 2, ',', '.') : '—' }}</dd></div>
                    </dl>
                    <a href="{{ route('funnel.contracts') }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-brand-500 hover:underline">Ver em Contratos</a>
                </x-card>
            @endif

            {{-- Histórico --}}
            <x-card title="Histórico de etapas" :padding="false">
                <ol class="divide-y divide-zinc-100">
                    @foreach ($o->histories as $history)
                        <li class="flex gap-3 px-5 py-3 text-sm" wire:key="h-{{ $history->id }}">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500"></span>
                            <div class="min-w-0 flex-1">
                                <div class="text-zinc-900">
                                    @if ($history->from_stage)
                                        {{ $history->from_stage->label() }} <span class="text-zinc-400">→</span>
                                    @endif
                                    <strong>{{ $history->to_stage->label() }}</strong>
                                </div>
                                @if ($history->note)<div class="text-zinc-600">{{ $history->note }}</div>@endif
                                <div class="text-xs text-zinc-500">{{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->user?->name ?? 'Sistema' }}</div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>

        <div class="flex flex-col gap-6">
            <x-card title="Contato">
                <dl class="flex flex-col gap-3 text-sm">
                    <div><dt class="text-zinc-500">Telefone</dt><dd class="tabular-nums text-zinc-900">{{ \App\Support\Phone::format($lead->phone) ?: '—' }}</dd></div>
                    @if ($lead->whatsapp || $lead->phone)
                        <div><a href="{{ \App\Support\Phone::whatsappUrl($lead->whatsapp ?? $lead->phone) }}" target="_blank" rel="noopener" class="font-medium text-success-500 hover:underline">Conversar no WhatsApp</a></div>
                    @endif
                    <div><dt class="text-zinc-500">Cidade</dt><dd class="text-zinc-900">{{ $lead->city?->label() ?? '—' }}{{ $lead->neighborhood ? ' · '.$lead->neighborhood : '' }}</dd></div>
                    <div><dt class="text-zinc-500">Origem</dt><dd class="text-zinc-900">{{ $o->source?->name ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">Indicado por</dt><dd class="text-zinc-900">{{ $o->indicator?->name ?? '—' }}</dd></div>
                    @if ($lead->document())
                        <div><dt class="text-zinc-500">{{ $lead->type->documentLabel() }}</dt><dd class="tabular-nums text-zinc-900">{{ \App\Support\Document::masked($lead->document()) }}</dd></div>
                    @endif
                </dl>
                @can('update', $lead)
                    <flux:button size="sm" class="mt-4" icon="pencil-square" wire:click="$dispatch('lead-form:edit', { id: {{ $lead->id }} })">Editar contato</flux:button>
                @endcan
            </x-card>

            <x-card title="Visitas" :padding="false">
                @if ($canUpdate && ! $closed && auth()->user()->can('agenda.manage'))
                    <x-slot:actions>
                        <flux:button size="sm" icon="plus" wire:click="$dispatch('visit-form:create', { leadId: {{ $lead->id }}, opportunityId: {{ $o->id }} })">Agendar</flux:button>
                    </x-slot:actions>
                @endif
                @forelse ($o->visits as $visit)
                    <div class="border-b border-zinc-100 px-5 py-3 text-sm last:border-0" wire:key="vis-{{ $visit->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('agenda.index', ['dia' => $visit->scheduled_at->toDateString()]) }}" wire:navigate class="text-zinc-900 hover:underline">{{ $visit->scheduled_at->format('d/m/Y H:i') }} · {{ $visit->type->label() }}</a>
                            <flux:badge size="sm" :color="$visit->status->color()">{{ $visit->status->label() }}</flux:badge>
                        </div>
                        @if ($visit->outcome)<div class="text-zinc-600">{{ \Illuminate\Support\Str::limit($visit->outcome, 160) }}</div>@endif
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-zinc-500">Nenhuma visita.</p>
                @endforelse
            </x-card>

            <x-card title="Propostas" :padding="false">
                @forelse ($o->proposals as $item)
                    <div class="border-b border-zinc-100 px-5 py-3 text-sm last:border-0" wire:key="p-{{ $item->id }}">
                        <div class="flex justify-between">
                            <span class="text-zinc-900">{{ $item->presented_at->format('d/m/Y') }}</span>
                            <span class="tabular-nums font-medium">{{ $item->amount ? 'R$ '.number_format((float) $item->amount, 2, ',', '.') : '' }}</span>
                        </div>
                        @if ($item->description)<div class="text-zinc-600">{{ $item->description }}</div>@endif
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-zinc-500">Nenhuma proposta registrada.</p>
                @endforelse
            </x-card>
        </div>
    </div>

    <livewire:funnel.lead-form />
    @can('agenda.manage')
        <livewire:agenda.visit-form />
    @endcan

    {{-- Modais --}}
    <flux:modal name="move-stage" class="w-full max-w-md">
        <form wire:submit="saveMove" class="flex flex-col gap-4">
            <flux:heading size="lg">Mover para {{ \App\Enums\OpportunityStage::tryFrom($move['stage'])?->label() }}</flux:heading>
            @if (\App\Enums\OpportunityStage::tryFrom($move['stage'])?->requiresReason())
                <flux:select wire:model="move.loss_reason_id" label="Motivo" required>
                    <flux:select.option value="">Selecione</flux:select.option>
                    @foreach ($lossReasons as $reason)
                        <flux:select.option :value="$reason->id">{{ $reason->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:textarea wire:model="move.note" label="Observação (opcional)" rows="2" />
            @error('move.stage') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Confirmar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="proposal-form" class="w-full max-w-md">
        <form wire:submit="saveProposal" class="flex flex-col gap-4">
            <flux:heading size="lg">Registrar proposta</flux:heading>
            <flux:input wire:model="proposal.presented_at" type="date" label="Data da apresentação" required />
            <flux:input wire:model="proposal.amount" type="number" min="0" step="0.01" label="Valor proposto (R$)" />
            <flux:textarea wire:model="proposal.description" label="Descrição" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Registrar</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="contract-form" class="w-full max-w-lg">
        <form wire:submit="saveContract" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:heading size="lg">Registrar contrato</flux:heading>
                <flux:text class="mt-1">O valor contratado não é contado como receita recebida. Recebimentos são lançados depois, em Contratos.</flux:text>
            </div>
            <flux:radio.group wire:model.live="contract.status" variant="segmented" class="sm:col-span-2" aria-label="Situação do contrato">
                <flux:radio value="signed" label="Assinado" />
                <flux:radio value="pending" label="Aguardando conclusão" />
            </flux:radio.group>
            <flux:input wire:model="contract.number" label="Número do contrato (opcional)" />
            @if ($contract['status'] === 'signed')
                <flux:input wire:model="contract.signed_at" type="date" label="Data de assinatura" required />
            @endif
            <flux:input wire:model="contract.contract_value" type="number" min="0" step="0.01" label="Valor do contrato (R$)" required />
            <flux:input wire:model="contract.expected_revenue" type="number" min="0" step="0.01" label="Receita prevista (R$)" description="Honorários esperados para o escritório." />
            <flux:textarea wire:model="contract.notes" label="Observações" rows="2" class="sm:col-span-2" />
            <div class="flex justify-end gap-2 sm:col-span-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Registrar contrato</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
