@props([
    'title',
    'subtitle' => null,
    'icon' => null,
    'period' => false,
])

<header class="flex flex-col gap-4 border-b border-zinc-200 pb-5 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex items-start gap-3">
        @if ($icon)
            <flux:icon :name="$icon" class="mt-1 size-7 shrink-0 text-brand-900" />
        @endif
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-brand-900 sm:text-[1.75rem]">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-sm text-zinc-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        {{ $actions ?? '' }}

        @if ($period)
            <livewire:period-selector />
        @endif

        <div class="hidden items-center lg:flex">
            <livewire:notification-bell />
        </div>
    </div>
</header>
