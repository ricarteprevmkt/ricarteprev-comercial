@props([
    'name',
    'src' => null,
    'size' => 'md',
])

@php
    $sizes = ['sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-16 text-lg'];
    $initials = \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($p) => mb_substr($p, 0, 1))->pipe(fn ($c) => $c->first().($c->count() > 1 ? $c->last() : ''));
@endphp

@if ($src)
    <img src="{{ $src }}" alt="" {{ $attributes->class([$sizes[$size], 'shrink-0 rounded-full object-cover ring-1 ring-zinc-200']) }}>
@else
    <span {{ $attributes->class([$sizes[$size], 'flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold uppercase text-brand-900']) }} aria-hidden="true">{{ $initials }}</span>
@endif
