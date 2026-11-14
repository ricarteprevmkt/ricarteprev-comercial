@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'delta' => null,        // variação % em relação ao período anterior
    'invertDelta' => false, // true quando cair é bom (ex.: custo)
    'tone' => null,         // warning|danger
])

@php
    $good = $delta === null ? null : ($invertDelta ? $delta < 0 : $delta > 0);
@endphp

<div {{ $attributes->class([
    'flex flex-col gap-2 rounded-xl border bg-white p-4 shadow-xs',
    'border-zinc-200' => ! $tone,
    'border-gold-400' => $tone === 'warning',
    'border-red-300' => $tone === 'danger',
]) }}>
    <div class="flex items-center gap-2 text-sm font-medium text-zinc-600">
        @if ($icon)
            <span class="flex size-8 items-center justify-center rounded-full bg-brand-50 text-brand-900"><flux:icon :name="$icon" class="size-4" /></span>
        @endif
        <span>{{ $label }}</span>
    </div>
    <div class="flex items-end justify-between gap-2">
        <span class="text-2xl font-bold tabular-nums text-brand-900 sm:text-[1.75rem]">{{ $value }}</span>
        @if ($delta !== null)
            <span @class(['flex items-center gap-0.5 text-sm font-semibold tabular-nums', 'text-success-500' => $good, 'text-red-600' => ! $good])
                title="Em relação ao período anterior">
                <flux:icon :name="$delta >= 0 ? 'arrow-up' : 'arrow-down'" variant="micro" />
                {{ number_format(abs($delta), 1, ',', '.') }}%
            </span>
        @endif
    </div>
    @if ($hint)
        <span class="text-xs text-zinc-500">{{ $hint }}</span>
    @endif
</div>
