<div class="flex flex-col gap-6">
    <x-page-header title="Usuários" subtitle="Quem acessa o sistema, com qual perfil e se o acesso está ativo.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Novo usuário</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-wrap gap-3 border-b border-zinc-100 p-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nome ou e-mail" class="max-w-xs" aria-label="Buscar" />
            <flux:select wire:model.live="roleFilter" class="max-w-48" aria-label="Perfil">
                <flux:select.option value="">Todos os perfis</flux:select.option>
                @foreach ($roles as $role)
                    <flux:select.option :value="$role->value">{{ $role->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="statusFilter" class="max-w-40" aria-label="Status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="ativo">Ativos</flux:select.option>
                <flux:select.option value="inativo">Inativos</flux:select.option>
            </flux:select>
        </div>

        @if ($this->users->isEmpty())
            <x-empty-state icon="users" title="Nenhum usuário encontrado" description="Ajuste os filtros ou cadastre um novo usuário." />
        @else
            <flux:table :paginate="$this->users" class="px-4">
                <flux:table.columns>
                    <flux:table.column>Nome</flux:table.column>
                    <flux:table.column class="max-md:hidden">Telefone</flux:table.column>
                    <flux:table.column>Perfil</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column class="max-lg:hidden">Último acesso</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">Ações</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->users as $user)
                        @php($role = $user->primaryRole())
                        <flux:table.row :key="$user->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$user->name" size="sm" />
                                    <div class="min-w-0">
                                        <div class="font-medium text-zinc-900">{{ $user->name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="max-md:hidden tabular-nums">{{ \App\Support\Phone::format($user->phone) ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$role === \App\Enums\Role::Admin ? 'blue' : 'zinc'">{{ $role?->label() ?? 'Sem perfil' }}</flux:badge>
                                @if ($user->executive_exists)
                                    <span class="ms-1 text-xs text-zinc-500">vinculado a executivo</span>
                                @elseif ($user->regional_indicator_exists)
                                    <span class="ms-1 text-xs text-zinc-500">vinculado a parceiro</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$user->active ? 'green' : 'zinc'">{{ $user->active ? 'Ativo' : 'Inativo' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="max-lg:hidden text-zinc-500">{{ $user->last_login_at?->diffForHumans() ?? 'Nunca entrou' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="Ações para {{ $user->name }}" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $user->id }})">Editar</flux:menu.item>
                                        @unless ($user->is(auth()->user()))
                                            <flux:menu.item :icon="$user->active ? 'no-symbol' : 'check-circle'" wire:click="toggleActive({{ $user->id }})" wire:confirm="{{ $user->active ? 'Desativar o acesso de '.$user->name.'?' : 'Reativar o acesso de '.$user->name.'?' }}">
                                                {{ $user->active ? 'Desativar acesso' : 'Reativar acesso' }}
                                            </flux:menu.item>
                                        @endunless
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-card>

    <flux:modal name="user-form" class="w-full max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Editar usuário' : 'Novo usuário' }}</flux:heading>
                <flux:text class="mt-1">Executivos e parceiros com acesso também podem ser criados pelos próprios cadastros.</flux:text>
            </div>

            <flux:input wire:model="form.name" label="Nome completo" required />
            <flux:input wire:model="form.email" type="email" label="E-mail de acesso" required />
            <flux:input wire:model="form.phone" label="Telefone" placeholder="(88) 99999-0000" />

            <flux:select wire:model="form.role" label="Perfil">
                @foreach ($roles as $role)
                    <flux:select.option :value="$role->value">{{ $role->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.password" type="password" viewable
                :label="$editingId ? 'Nova senha (opcional)' : 'Senha inicial (opcional)'"
                :description="$editingId ? 'Deixe em branco para manter a senha atual.' : 'Em branco, o usuário recebe por e-mail um link para criar a própria senha.'" />

            <flux:switch wire:model="form.active" label="Acesso ativo" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
