<div class="flex flex-wrap items-end gap-2">
    <flux:select wire:model.live="preset" size="sm" class="min-w-44" aria-label="Período">
        @foreach ($presets as $value => $name)
            <flux:select.option :value="$value">{{ $name }}</flux:select.option>
        @endforeach
    </flux:select>

    @if ($preset === 'personalizado')
        <form wire:submit="applyCustom" class="flex flex-wrap items-end gap-2">
            <flux:input type="date" size="sm" wire:model="start" aria-label="Data inicial" />
            <flux:input type="date" size="sm" wire:model="end" aria-label="Data final" />
            <flux:button type="submit" size="sm" variant="primary">Aplicar</flux:button>
        </form>
        @error('end') <p class="w-full text-xs text-red-600">{{ $message }}</p> @enderror
    @endif
</div>
