@php
    $tabs = [
        ['route' => 'adm.index', 'label' => 'Painel', 'active' => 'adm.index'],
        ['route' => 'adm.processes', 'label' => 'Processos', 'active' => 'adm.processes*'],
        ['route' => 'adm.deadlines', 'label' => 'Prazos', 'active' => 'adm.deadlines'],
        ['route' => 'adm.pending', 'label' => 'Pendências', 'active' => 'adm.pending'],
    ];
@endphp

<nav aria-label="Seções do administrativo" class="-mb-px flex gap-1 overflow-x-auto border-b border-zinc-200">
    @foreach ($tabs as $tab)
        @php($active = request()->routeIs($tab['active']))
        <a href="{{ route($tab['route']) }}" wire:navigate
            @class([
                'shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium transition',
                'border-gold-400 text-brand-900' => $active,
                'border-transparent text-zinc-500 hover:text-brand-900' => ! $active,
            ])
            @if ($active) aria-current="page" @endif>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>

@unless (\App\Services\C2\C2Client::enabled())
    <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">A integração com o C2 Prev está desligada: os dados abaixo não estão sendo atualizados.</p>
@endunless
