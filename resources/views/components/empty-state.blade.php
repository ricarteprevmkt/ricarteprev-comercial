@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div class="flex flex-col items-center gap-2 px-6 py-12 text-center">
    <span class="flex size-11 items-center justify-center rounded-full bg-brand-50 text-brand-700">
        <flux:icon :name="$icon" class="size-5" />
    </span>
    <p class="font-semibold text-brand-900">{{ $title }}</p>
    @if ($description)
        <p class="max-w-md text-sm text-zinc-500">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
