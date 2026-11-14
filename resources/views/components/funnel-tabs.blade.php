@php
    $tabs = [
        ['route' => 'funnel.index', 'label' => 'Painel do funil'],
        ['route' => 'funnel.opportunities', 'label' => 'Oportunidades'],
        ['route' => 'funnel.leads', 'label' => 'Contatos'],
        ['route' => 'funnel.contracts', 'label' => 'Contratos'],
    ];
@endphp

<nav aria-label="Seções do funil" class="-mb-px flex gap-1 overflow-x-auto border-b border-zinc-200">
    @foreach ($tabs as $tab)
        @php($active = request()->routeIs($tab['route']) || ($tab['route'] === 'funnel.opportunities' && request()->routeIs('funnel.opportunities.show')))
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
