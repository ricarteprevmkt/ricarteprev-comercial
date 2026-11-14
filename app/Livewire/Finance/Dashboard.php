<?php

namespace App\Livewire\Finance;

use App\Metrics\FinanceMetrics;
use App\Metrics\FunnelMetrics;
use App\Models\Region;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Custos e resultados da operação comercial. Números de FinanceMetrics.
 */
#[Title('Custos e resultados')]
class Dashboard extends Component
{
    #[Url(as: 'regiao', except: '')]
    public string $regionFilter = '';

    public function mount(): void
    {
        $this->authorize('finance.view');
    }

    private function metrics(): FinanceMetrics
    {
        return new FinanceMetrics(Period::current(), null, $this->regionFilter !== '' ? (int) $this->regionFilter : null);
    }

    #[On('period-changed')]
    public function periodChanged(): void
    {
        $this->pushCharts();
    }

    public function updatedRegionFilter(): void
    {
        $this->pushCharts();
    }

    private function pushCharts(): void
    {
        $m = $this->metrics();
        $this->dispatch('finance-monthly', data: $m->monthly());
        $this->dispatch('finance-categories', data: $this->donut($m->byCategory(), 'category', 'Total'));
        $this->dispatch('finance-sources', data: $this->donut($m->revenueBySource(), 'source', 'Receita'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{labels: list<string>, values: list<float>, total_label: string}
     */
    private function donut(array $rows, string $labelKey, string $totalLabel): array
    {
        return [
            'labels' => array_map(fn ($r) => (string) $r[$labelKey], $rows),
            'values' => array_map(fn ($r) => (float) $r['total'], $rows),
            'total_label' => $totalLabel,
        ];
    }

    public function render(): View
    {
        $m = $this->metrics();
        $s = $m->summary();
        $p = $m->previous()->summary();
        $byCategory = $m->byCategory();
        $bySource = $m->revenueBySource();

        return view('livewire.finance.dashboard', [
            'period' => Period::current(),
            's' => $s,
            'p' => $p,
            'delta' => fn ($a, $b) => $a === null || $b === null ? null : FunnelMetrics::delta((float) $a, (float) $b),
            'byCategory' => $byCategory,
            'bySource' => $bySource,
            'byRegion' => $m->byRegion(),
            'byExecutive' => $m->byExecutive(),
            'monthly' => $m->monthly(),
            'categoryDonut' => $this->donut($byCategory, 'category', 'Total'),
            'sourceDonut' => $this->donut($bySource, 'source', 'Receita'),
            'regions' => Region::query()->active()->orderBy('name')->get(),
        ]);
    }
}
