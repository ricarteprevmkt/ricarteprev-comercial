@php
    $tabs = array_values(array_filter([
        auth()->user()->can('finance.view') ? ['route' => 'finance.dashboard', 'label' => 'Painel'] : null,
        ['route' => 'finance.expenses', 'label' => 'Despesas'],
    ]));
@endphp

<nav aria-label="Seções de custos e resultados" class="-mb-px flex gap-1 overflow-x-auto border-b border-zinc-200">
    @foreach ($tabs as $tab)
        @php($active = request()->routeIs($tab['route']))
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
