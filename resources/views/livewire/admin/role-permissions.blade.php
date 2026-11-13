<div class="flex flex-col gap-6">
    <x-page-header title="Perfis e permissões" subtitle="Marque o que cada perfil pode ver e fazer. O administrador sempre tem acesso total." />

    <form wire:submit="save" class="flex flex-col gap-6">
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left">
                            <th scope="col" class="px-5 py-3 font-medium text-zinc-500">Permissão</th>
                            @foreach ($roles as $role)
                                <th scope="col" class="w-36 px-3 py-3 text-center font-semibold text-brand-900">
                                    {{ $role->label() }}
                                    <button type="button" wire:click="restoreDefaults('{{ $role->value }}')" class="mt-1 block w-full text-xs font-normal text-brand-500 hover:underline">restaurar padrão</button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group => $permissions)
                            <tr class="bg-zinc-50">
                                <th colspan="{{ count($roles) + 1 }}" scope="colgroup" class="px-5 py-2 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $group }}</th>
                            </tr>
                            @foreach ($permissions as $name => $description)
                                <tr class="border-b border-zinc-100 last:border-0">
                                    <td class="px-5 py-2.5">
                                        <div class="text-zinc-800">{{ $description }}</div>
                                        <div class="font-mono text-[11px] text-zinc-400">{{ $name }}</div>
                                    </td>
                                    @foreach ($roles as $role)
                                        <td class="px-3 py-2.5 text-center">
                                            <input type="checkbox" value="{{ $name }}" wire:model="matrix.{{ $role->value }}"
                                                id="perm-{{ $role->value }}-{{ $name }}"
                                                class="size-4 rounded border-zinc-300 text-brand-900 accent-brand-900 focus-visible:outline-2 focus-visible:outline-brand-500"
                                                aria-label="{{ $role->label() }}: {{ $description }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Salvar permissões</flux:button>
        </div>
    </form>
</div>
