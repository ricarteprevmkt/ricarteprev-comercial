@php
    $tabs = array_values(array_filter([
        auth()->user()->can('referrals.view_all') ? ['route' => 'referrals.index', 'label' => 'Painel regional', 'match' => ['referrals.index']] : null,
        ['route' => 'referrals.list', 'label' => 'Indicações', 'match' => ['referrals.list']],
        auth()->user()->can('indicators.manage') ? ['route' => 'referrals.indicators', 'label' => 'Parceiros', 'match' => ['referrals.indicators', 'referrals.indicators.show']] : null,
        auth()->user()->can('commissions.manage') ? ['route' => 'referrals.commissions', 'label' => 'Comissões', 'match' => ['referrals.commissions']] : null,
    ]));
@endphp

<nav aria-label="Seções de parceiros regionais" class="-mb-px flex gap-1 overflow-x-auto border-b border-zinc-200">
    @foreach ($tabs as $tab)
        @php($active = request()->routeIs(...$tab['match']))
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
