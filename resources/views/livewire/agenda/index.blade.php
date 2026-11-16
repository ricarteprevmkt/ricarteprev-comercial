@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $km = fn ($v) => number_format((float) $v, 1, ',', '.').' km';
    $s = $this->summary;
    $day = $this->day();
    $isToday = $day->isToday();
    $canManage = auth()->user()->can('agenda.manage');
    $label = $mode === 'semana'
        ? 'Semana de '.$day->startOfWeek()->format('d/m').' a '.$day->endOfWeek()->format('d/m/Y')
        : ($isToday ? 'Hoje, ' : '').$day->translatedFormat('d \d\e F \d\e Y');
@endphp

<div class="flex flex-col gap-6">
    <x-page-header title="Agenda e rotas" subtitle="Planejamento diário, visitas, deslocamentos e acompanhamento da equipe em campo." icon="calendar-days">
        <x-slot:actions>
            @if ($executives->isNotEmpty())
                <flux:select wire:model.live="executiveFilter" size="sm" class="min-w-48" aria-label="Executivo">
                    <flux:select.option value="">Toda a equipe</flux:select.option>
                    @foreach ($executives as $executive)
                        <flux:select.option :value="$executive->id">{{ $executive->user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Navegação de datas --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shiftDay(-1)" aria-label="Anterior" />
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shiftDay(1)" aria-label="Próximo" />
        </div>
        <span class="text-base font-semibold text-brand-900">{{ \Illuminate\Support\Str::ucfirst($label) }}</span>
        <input type="date" wire:model.live="date" class="rounded-lg border border-zinc-200 bg-white px-2 py-1 text-sm" aria-label="Escolher data">
        @unless ($isToday)
            <flux:button size="sm" wire:click="goToday">Hoje</flux:button>
        @endunless
        <flux:radio.group wire:model.live="mode" variant="segmented" size="sm" aria-label="Visualização">
            <flux:radio value="dia" label="Dia" />
            <flux:radio value="semana" label="Semana" />
        </flux:radio.group>
        <flux:spacer />
        @can('expenses.manage')
            <flux:button size="sm" icon="receipt-percent" wire:click="$dispatch('expense-form:create', { date: '{{ $date }}' })">Lançar despesa</flux:button>
        @endcan
        @if ($canManage)
            <flux:button size="sm" variant="primary" icon="plus" wire:click="$dispatch('visit-form:create', { date: '{{ $date }}' })">Agendar visita</flux:button>
        @endif
    </div>

    {{-- Resumo --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Visitas programadas" :value="$s['planned']" icon="calendar-days" :hint="$s['pending'].' pendente(s)'" />
        <x-stat label="Concluídas" :value="$s['done']" icon="check-circle"
            :hint="$s['planned'] ? number_format($s['done'] / $s['planned'] * 100, 0).'% das programadas' : 'nenhuma programada'" />
        <x-stat :label="$s['by_road'] ? 'Distância pelas estradas' : 'Distância estimada'" :value="$km($s['km_estimated'])" icon="map"
            :hint="($s['km_reported'] > 0 ? $km($s['km_reported']).' informados' : ($s['by_road'] ? 'rota planejada' : 'linha reta × fator de estrada')).($s['approximate'] ? ' · inclui locais aproximados' : '')" />
        <x-stat label="Custos do dia" :value="$brl($s['expenses_actual'])" icon="banknotes"
            :hint="$s['cost_estimated'] !== null ? 'estimado do deslocamento: '.$brl($s['cost_estimated']) : 'informe o custo por km em Parâmetros'" />
    </div>

    @if ($s['not_done'] > 0 || $s['without_result'] > 0)
        <div class="flex flex-wrap gap-3 text-sm">
            @if ($s['not_done'] > 0)
                <span class="rounded-full bg-red-50 px-3 py-1 font-medium text-red-700">{{ $s['not_done'] }} não realizada(s)</span>
            @endif
            @if ($s['without_result'] > 0)
                <span class="rounded-full bg-gold-100 px-3 py-1 font-medium text-gold-700">{{ $s['without_result'] }} sem resultado registrado</span>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-5">
        {{-- Agenda --}}
        <x-card :title="$mode === 'semana' ? 'Agenda da semana' : 'Agenda do dia'" :padding="false" class="xl:col-span-3">
            @if ($this->visits->isEmpty())
                <x-empty-state icon="calendar" title="Nenhuma visita {{ $mode === 'semana' ? 'nesta semana' : 'neste dia' }}"
                    :description="$servedCities === 0 ? 'Marque as cidades atendidas em Administração › Regiões e cidades para começar a agendar.' : 'Agende visitas para montar a rota.'">
                    @if ($canManage)
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="$dispatch('visit-form:create', { date: '{{ $date }}' })" class="mt-2">Agendar visita</flux:button>
                    @endif
                </x-empty-state>
            @else
                <ul class="divide-y divide-zinc-100">
                    @foreach ($this->visits as $visit)
                        @php($editable = $canManage && auth()->user()->can('update', $visit))
                        @if ($mode === 'semana' && ($loop->first || ! $visit->scheduled_at->isSameDay($this->visits[$loop->index - 1]->scheduled_at)))
                            <li class="bg-zinc-50 px-5 py-1.5 text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $visit->scheduled_at->translatedFormat('l, d/m') }}</li>
                        @endif
                        <li class="flex gap-3 px-5 py-3" wire:key="v-{{ $visit->id }}">
                            <div class="w-12 shrink-0 pt-0.5 text-sm font-semibold tabular-nums text-brand-900">{{ $visit->scheduled_at->format('H:i') }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($visit->opportunity_id)
                                        <a href="{{ route('funnel.opportunities.show', $visit->opportunity_id) }}" wire:navigate class="font-medium text-zinc-900 hover:underline">{{ $visit->displayName() }}</a>
                                    @else
                                        <span class="font-medium text-zinc-900">{{ $visit->displayName() }}</span>
                                    @endif
                                    <flux:badge size="sm" :color="$visit->status->color()">{{ $visit->status->label() }}</flux:badge>
                                    @if ($visit->priority === \App\Enums\VisitPriority::High)
                                        <flux:badge size="sm" color="red" icon="exclamation-triangle">Alta</flux:badge>
                                    @endif
                                </div>
                                <div class="text-sm text-zinc-500">
                                    {{ $visit->type->label() }} · {{ $visit->city?->name }}{{ $visit->neighborhood ? ' ('.$visit->neighborhood.')' : '' }}
                                    @if (! $executiveFilter && auth()->user()->can('agenda.view_all')) · {{ $visit->executive->user->name }} @endif
                                </div>
                                @if ($visit->status === \App\Enums\VisitStatus::Done && $visit->outcome)
                                    <div class="mt-1 text-sm text-zinc-700">{{ \Illuminate\Support\Str::limit($visit->outcome, 140) }}</div>
                                @elseif ($visit->status === \App\Enums\VisitStatus::NotDone)
                                    <div class="mt-1 text-sm text-red-700">{{ $visit->notDoneReason?->name }}{{ $visit->outcome ? ' · '.$visit->outcome : '' }}</div>
                                @elseif ($visit->notes)
                                    <div class="mt-1 text-sm text-zinc-600">{{ \Illuminate\Support\Str::limit($visit->notes, 140) }}</div>
                                @endif
                            </div>
                            @if ($editable)
                                <div class="flex shrink-0 items-start gap-1">
                                    @if (in_array($visit->status, [\App\Enums\VisitStatus::Scheduled, \App\Enums\VisitStatus::Confirmed], true) && $visit->scheduled_at->isToday())
                                        <flux:button size="sm" icon="play" x-data
                                            x-on:click="
                                                const go = (lat, lng) => $wire.start({{ $visit->id }}, lat, lng);
                                                if (! navigator.geolocation) { go(null, null); return; }
                                                navigator.geolocation.getCurrentPosition(p => go(p.coords.latitude, p.coords.longitude), () => go(null, null), { timeout: 8000, maximumAge: 60000 });
                                            ">Iniciar</flux:button>
                                    @endif
                                    @if ($visit->status->isPending())
                                        <flux:button size="sm" variant="primary" icon="check" wire:click="openResult({{ $visit->id }})">Registrar</flux:button>
                                    @endif
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" aria-label="Mais ações para {{ $visit->displayName() }}" />
                                        <flux:menu>
                                            @if ($visit->status === \App\Enums\VisitStatus::Scheduled)
                                                <flux:menu.item icon="check-badge" wire:click="confirm({{ $visit->id }})">Confirmar</flux:menu.item>
                                            @endif
                                            @if ($visit->status->isPending())
                                                <flux:menu.item icon="pencil-square" wire:click="$dispatch('visit-form:edit', { id: {{ $visit->id }} })">Editar</flux:menu.item>
                                                <flux:menu.item icon="x-circle" wire:click="openNotDone({{ $visit->id }})">Não realizada</flux:menu.item>
                                            @endif
                                            @if ($visit->status->isPending() || $visit->status === \App\Enums\VisitStatus::NotDone)
                                                @unless ($visit->rescheduled_to_id)
                                                    <flux:menu.item icon="arrow-path" wire:click="openReschedule({{ $visit->id }})">Reagendar</flux:menu.item>
                                                @endunless
                                            @endif
                                            @can('expenses.manage')
                                                <flux:menu.item icon="receipt-percent" wire:click="$dispatch('expense-form:create', { visitId: {{ $visit->id }} })">Lançar despesa</flux:menu.item>
                                            @endcan
                                            @if (in_array($visit->status, [\App\Enums\VisitStatus::Scheduled, \App\Enums\VisitStatus::Confirmed], true))
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger" wire:click="cancel({{ $visit->id }})" wire:confirm="Cancelar esta visita?">Cancelar visita</flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Mapa --}}
        <x-card :title="$mode === 'semana' ? 'Rotas da semana' : 'Rota do dia'" :padding="false" class="xl:col-span-2">
            <div wire:ignore x-data="routeMap(@js($mapData))">
                <div x-ref="canvas" class="h-80 w-full xl:h-[26rem]"
                    data-tiles="{{ config('services.map.tiles') }}" data-attribution="{{ config('services.map.attribution') }}"></div>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 border-t border-zinc-100 px-5 py-3 text-xs text-zinc-600">
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#009a66]"></span>Concluída</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#2f6fd0]"></span>Em andamento</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#e9a83a]"></span>Pendente</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-[#dc2626]"></span>Não realizada</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full border-2 border-white bg-brand-900 ring-1 ring-zinc-300"></span>Último check-in</span>
            </div>
            <p class="border-t border-zinc-100 px-5 py-2 text-xs text-zinc-500">
                {{ $s['by_road'] ? 'Distância calculada pelas estradas (OpenRouteService); no mapa, as linhas ligam as paradas em ordem.' : 'Distância estimada em linha reta × fator de estrada.' }} Não há rastreamento em tempo real: a posição do executivo é a do último check-in.
            </p>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @if ($this->team->isNotEmpty())
            <x-card title="Executivos em campo" :padding="false">
                <ul class="divide-y divide-zinc-100">
                    @foreach ($this->team as $row)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <x-avatar :name="$row['executive']->user->name" size="sm" :src="$row['executive']->photo_path ? route('photos.executive', $row['executive']) : null" />
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-zinc-900">{{ $row['executive']->user->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $row['last_checkin'] ? 'Check-in às '.$row['last_checkin']->checkin_at?->format('H:i').' · '.$row['last_checkin']->city?->name : 'Sem check-in hoje' }}</div>
                            </div>
                            <div class="w-28">
                                <div class="text-end text-xs tabular-nums text-zinc-600">{{ $row['done'] }}/{{ $row['planned'] }}</div>
                                <div class="mt-1 h-1.5 rounded-full bg-zinc-100"><div class="h-full rounded-full bg-success-500" style="width: {{ $row['planned'] ? $row['done'] / $row['planned'] * 100 : 0 }}%"></div></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card title="Deslocamento e custos do dia" :padding="false">
            <dl class="divide-y divide-zinc-100 text-sm">
                <div class="flex justify-between px-5 py-2.5"><dt class="text-zinc-600">Km estimados (rota)</dt><dd class="tabular-nums">{{ $km($routeKm) }}</dd></div>
                <div class="flex justify-between px-5 py-2.5"><dt class="text-zinc-600">Km informados nas visitas</dt><dd class="tabular-nums">{{ $s['km_reported'] > 0 ? $km($s['km_reported']) : '—' }}</dd></div>
                <div class="flex justify-between px-5 py-2.5"><dt class="text-zinc-600">Custo estimado do deslocamento</dt><dd class="tabular-nums">{{ $s['cost_estimated'] !== null ? $brl($s['cost_estimated']) : '—' }}</dd></div>
            </dl>
            <div class="border-t border-zinc-200 px-5 pb-1 pt-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Despesas lançadas</div>
            <ul class="divide-y divide-zinc-100 text-sm">
                @forelse ($this->expenses as $expense)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5" wire:key="e-{{ $expense->id }}">
                        <div class="min-w-0">
                            <div class="text-zinc-900">{{ $expense->category->name }} · {{ $expense->description }}</div>
                            <div class="text-xs text-zinc-500">{{ $expense->executive?->user->name ?? 'Geral' }} · {{ $expense->status->label() }}</div>
                        </div>
                        <span class="tabular-nums font-medium">{{ $brl($expense->amount) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-4 text-center text-zinc-500">Nenhuma despesa lançada neste dia.</li>
                @endforelse
            </ul>
            <div class="flex justify-between border-t border-zinc-200 bg-zinc-50 px-5 py-3 font-semibold">
                <span>Total efetivo (sem recusadas)</span>
                <span class="tabular-nums">{{ $brl($s['expenses_actual']) }}</span>
            </div>
        </x-card>
    </div>

    <livewire:agenda.visit-form />
    <livewire:agenda.expense-form />

    {{-- Registrar resultado --}}
    <flux:modal name="visit-result" class="w-full max-w-lg">
        <form wire:submit="saveResult" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:heading size="lg">Registrar visita</flux:heading>
                <flux:text class="mt-1">{{ $selected?->displayName() }} · {{ $selected?->scheduled_at->format('d/m/Y') }}</flux:text>
            </div>
            <flux:input wire:model="result.started_at" type="time" label="Início" required />
            <flux:input wire:model="result.finished_at" type="time" label="Término" required />
            <flux:textarea wire:model="result.outcome" label="Resultado" rows="3" required class="sm:col-span-2" placeholder="O que foi conversado e combinado" />
            <flux:input wire:model="result.next_action" label="Próxima ação" />
            <flux:input wire:model="result.next_contact_at" type="date" label="Próximo contato" />
            <flux:input wire:model="result.km_reported" type="number" min="0" step="0.1" inputmode="decimal" label="Km percorridos (opcional)" />
            @if ($selected && $selected->lead_id && ! $selected->opportunity_id)
                <div class="flex items-end pb-2">
                    <flux:checkbox wire:model="result.open_opportunity" label="Abrir oportunidade para este contato" />
                </div>
            @endif
            <div class="flex justify-end gap-2 sm:col-span-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Concluir visita</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Não realizada --}}
    <flux:modal name="visit-not-done" class="w-full max-w-md">
        <form wire:submit="saveNotDone" class="flex flex-col gap-4">
            <flux:heading size="lg">Visita não realizada</flux:heading>
            <flux:select wire:model="notDone.reason_id" label="Motivo" required>
                <flux:select.option value="">Selecione</flux:select.option>
                @foreach ($reasons as $reason)
                    <flux:select.option :value="$reason->id">{{ $reason->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="notDone.note" label="Observação (opcional)" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Reagendar --}}
    <flux:modal name="visit-reschedule" class="w-full max-w-md">
        <form wire:submit="saveReschedule" class="grid gap-4 sm:grid-cols-2">
            <flux:heading size="lg" class="sm:col-span-2">Reagendar visita</flux:heading>
            <flux:input wire:model="reschedule.date" type="date" label="Nova data" required />
            <flux:input wire:model="reschedule.time" type="time" label="Novo horário" required />
            <flux:textarea wire:model="reschedule.note" label="Motivo (opcional)" rows="2" class="sm:col-span-2" />
            <div class="flex justify-end gap-2 sm:col-span-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Reagendar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
