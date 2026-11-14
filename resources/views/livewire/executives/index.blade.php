<div class="flex flex-col gap-6">
    <x-page-header title="Executivos" subtitle="Equipe comercial, cidades de atuação, metas mensais e desempenho individual.">
        <x-slot:actions>
            @can('executives.manage')
                <flux:button variant="primary" icon="plus" wire:click="create">Novo executivo</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nome ou e-mail" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="statusFilter" class="max-w-40" aria-label="Status">
                <flux:select.option value="">Todos os status</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="regionFilter" class="max-w-48" aria-label="Região">
                <flux:select.option value="">Todas as regiões</flux:select.option>
                @foreach ($filterRegions as $region)
                    <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->executives->isEmpty())
            <x-empty-state icon="user" title="Nenhum executivo cadastrado"
                description="Cadastre os executivos comerciais para começar a registrar visitas, oportunidades e metas.">
                @can('executives.manage')
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="create" class="mt-2">Cadastrar executivo</flux:button>
                @endcan
            </x-empty-state>
        @else
            <flux:table :paginate="$this->executives" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Executivo</flux:table.column>
                    <flux:table.column class="max-md:hidden">Atuação</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column class="max-lg:hidden" align="end">Meta mensal</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Admissão</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->executives as $executive)
                        <flux:table.row :key="$executive->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$executive->user->name" :src="$executive->photo_path ? route('photos.executive', $executive) : null" />
                                    <div class="min-w-0">
                                        <a href="{{ route('executives.show', $executive) }}" wire:navigate class="font-medium text-zinc-900 hover:underline">{{ $executive->user->name }}</a>
                                        <div class="text-xs text-zinc-500">{{ $executive->user->email }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden">
                                <div>{{ $executive->city?->label() ?? '—' }}</div>
                                <div class="text-xs text-zinc-500">{{ $executive->region?->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$executive->status->color()">{{ $executive->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden tabular-nums" align="end">
                                @if ($executive->monthly_visits_target !== null || $executive->monthly_contracts_target !== null)
                                    {{ $executive->monthly_visits_target ?? '—' }} visitas · {{ $executive->monthly_contracts_target ?? '—' }} contratos
                                @else
                                    <span class="text-zinc-400">Sem meta</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden">{{ $executive->hired_at?->format('d/m/Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @canany(['executives.manage', 'goals.manage'])
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Ações para {{ $executive->user->name }}" />
                                        <flux:menu>
                                            <flux:menu.item icon="chart-bar" :href="route('executives.show', $executive)" wire:navigate>Ver desempenho</flux:menu.item>
                                            @can('executives.manage')
                                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $executive->id }})">Editar cadastro</flux:menu.item>
                                            @endcan
                                            @can('goals.manage')
                                                <flux:menu.item icon="flag" wire:click="openGoals({{ $executive->id }})">Metas por mês</flux:menu.item>
                                            @endcan
                                        </flux:menu>
                                    </flux:dropdown>
                                @endcanany
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    @can('executives.manage')
        <flux:modal name="executive-form" class="w-full max-w-2xl">
            <form wire:submit="save" class="flex flex-col gap-6">
                <flux:heading size="lg">{{ $editingId ? 'Editar executivo' : 'Novo executivo' }}</flux:heading>

                <fieldset class="grid gap-4 sm:grid-cols-2">
                    <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Dados pessoais e acesso</legend>
                    <div class="flex items-center gap-4 sm:col-span-2">
                        @if ($photo && method_exists($photo, 'isPreviewable') && $photo->isPreviewable())
                            <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 rounded-full object-cover ring-1 ring-zinc-200">
                        @elseif ($editingId && ($current = \App\Models\Executive::find($editingId))?->photo_path)
                            <img src="{{ route('photos.executive', $current) }}" alt="" class="size-16 rounded-full object-cover ring-1 ring-zinc-200">
                        @else
                            <x-avatar :name="$form['name'] ?: 'Novo'" size="lg" />
                        @endif
                        <flux:input type="file" wire:model="photo" label="Foto" accept="image/jpeg,image/png,image/webp" description="JPG, PNG ou WebP, até 2 MB." />
                    </div>
                    <flux:input wire:model="form.name" label="Nome completo" required class="sm:col-span-2" />
                    <flux:input wire:model="form.email" type="email" label="E-mail de acesso" required />
                    <flux:input wire:model="form.phone" label="Telefone" placeholder="(88) 99999-0000" />
                    <flux:input wire:model="form.cpf" label="CPF (opcional)" placeholder="000.000.000-00" description="Guardado criptografado." />
                    @unless ($editingId)
                        <flux:input wire:model="form.password" type="password" viewable label="Senha inicial (opcional)" description="Em branco, enviamos um link para criar a senha." />
                    @endunless
                </fieldset>

                <fieldset class="grid gap-4 sm:grid-cols-2">
                    <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-2">Atuação</legend>
                    <flux:select wire:model.live="form.city_id" label="Cidade base">
                        <flux:select.option value="">Selecione</flux:select.option>
                        @foreach ($cities as $city)
                            <flux:select.option :value="$city->id">{{ $city->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="form.region_id" label="Região de atuação">
                        <flux:select.option value="">Selecione</flux:select.option>
                        @foreach ($regions as $region)
                            <flux:select.option :value="$region->id">{{ $region->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($cities->isEmpty())
                        <p class="text-xs text-amber-700 sm:col-span-2">Nenhuma cidade marcada como atendida. Marque as cidades em Administração › Regiões e cidades.</p>
                    @endif
                    <flux:input wire:model="form.hired_at" type="date" label="Data de admissão" />
                    <flux:select wire:model="form.status" label="Status" description="Desligado perde o acesso ao sistema.">
                        @foreach ($statuses as $status)
                            <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </fieldset>

                <fieldset class="grid gap-4 sm:grid-cols-3">
                    <legend class="mb-2 text-sm font-semibold text-brand-900 sm:col-span-3">Metas padrão e remuneração</legend>
                    <flux:input wire:model="form.monthly_visits_target" type="number" min="0" label="Visitas por mês" />
                    <flux:input wire:model="form.monthly_contracts_target" type="number" min="0" label="Contratos por mês" />
                    <flux:input wire:model="form.cost_allowance" type="number" min="0" step="0.01" label="Ajuda de custo (R$)" />
                    <flux:textarea wire:model="form.compensation_notes" label="Parâmetros de remuneração (opcional)" rows="2" class="sm:col-span-3" />
                </fieldset>

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Salvar</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    @can('goals.manage')
        <flux:modal name="goals" class="w-full max-w-lg">
            <div class="flex flex-col gap-5">
                <div>
                    <flux:heading size="lg">Metas por mês</flux:heading>
                    <flux:text class="mt-1">{{ $this->goalsExecutive?->user->name }}. Meses sem meta própria usam a meta padrão do cadastro.</flux:text>
                </div>

                <form wire:submit="saveGoal" class="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                    <flux:input wire:model="goalForm.month" type="month" label="Mês" />
                    <flux:input wire:model="goalForm.visits_target" type="number" min="0" label="Visitas" />
                    <flux:input wire:model="goalForm.contracts_target" type="number" min="0" label="Contratos" />
                    <flux:button type="submit" variant="primary">Salvar</flux:button>
                </form>

                @if ($this->goals->isEmpty())
                    <p class="rounded-lg bg-zinc-50 px-4 py-6 text-center text-sm text-zinc-500">Nenhuma meta mensal específica.</p>
                @else
                    <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200">
                        @foreach ($this->goals as $goal)
                            <li class="flex items-center justify-between px-4 py-2.5 text-sm" wire:key="goal-{{ $goal->id }}">
                                <span class="font-medium capitalize text-zinc-800">{{ $goal->month->translatedFormat('F \d\e Y') }}</span>
                                <span class="tabular-nums text-zinc-600">{{ $goal->visits_target }} visitas · {{ $goal->contracts_target }} contratos</span>
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteGoal({{ $goal->id }})" wire:confirm="Remover esta meta?" aria-label="Remover meta" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </flux:modal>
    @endcan
</div>
