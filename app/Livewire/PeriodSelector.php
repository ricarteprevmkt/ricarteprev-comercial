<?php

namespace App\Livewire;

use App\Support\Period;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Seletor de período compartilhado por todos os painéis. Grava a escolha na
 * sessão e avisa os componentes da página pelo evento "period-changed".
 */
class PeriodSelector extends Component
{
    public string $preset = Period::DEFAULT_PRESET;

    public ?string $start = null;

    public ?string $end = null;

    public function mount(): void
    {
        $period = Period::current();
        $this->preset = $period->preset;
        $this->start = $period->start->toDateString();
        $this->end = $period->end->toDateString();
    }

    public function updatedPreset(string $value): void
    {
        if ($value === 'personalizado') {
            return; // espera as datas
        }

        $this->apply(Period::fromPreset($value));
    }

    public function applyCustom(): void
    {
        $this->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ], attributes: ['start' => 'data inicial', 'end' => 'data final']);

        try {
            $this->apply(Period::custom($this->start, $this->end));
        } catch (InvalidArgumentException $e) {
            $this->addError('end', $e->getMessage());
        }
    }

    private function apply(Period $period): void
    {
        $period->remember();
        $this->start = $period->start->toDateString();
        $this->end = $period->end->toDateString();
        $this->dispatch('period-changed', start: $this->start, end: $this->end);
    }

    public function render(): View
    {
        return view('livewire.period-selector', [
            'presets' => Period::PRESETS,
            'label' => Period::current()->label(),
        ]);
    }
}
