<?php

namespace App\Livewire\Reports;

use App\Enums\OpportunityStage;
use App\Livewire\Concerns\LoadsLocationOptions;
use App\Models\Catalogs\LeadSource;
use App\Models\Region;
use App\Models\RegionalIndicator;
use App\Reports\Report;
use App\Reports\ReportFilterLabels;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Support\Period;
use App\Support\Visibility;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Central de relatórios: escolha, filtros, prévia na tela e exportação.
 */
#[Title('Relatórios')]
class Index extends Component
{
    use LoadsLocationOptions;

    public const PREVIEW_LIMIT = 200;

    #[Url(as: 'relatorio')]
    public string $report = 'desempenho-geral';

    /** @var array<string, string> */
    #[Url(as: 'f')]
    public array $filters = ['executive' => '', 'indicator' => '', 'city' => '', 'region' => '', 'source' => '', 'stage' => ''];

    public function mount(): void
    {
        $this->authorize('reports.view');

        if (ReportRegistry::find($this->report) === null) {
            $this->report = 'desempenho-geral';
        }
    }

    #[On('period-changed')]
    public function periodChanged(): void {}

    public function select(string $key): void
    {
        if (ReportRegistry::find($key)) {
            $this->report = $key;
        }
    }

    public function clearFilters(): void
    {
        $this->filters = array_map(fn () => '', $this->filters);
    }

    private function definition(): Report
    {
        return ReportRegistry::find($this->report) ?? ReportRegistry::all()[0];
    }

    private function currentFilters(): ReportFilters
    {
        $period = Period::current();
        $allowed = array_flip($this->definition()->filters());
        $input = array_intersect_key($this->filters, $allowed);

        return ReportFilters::fromArray($input + [
            'start' => $period->start->toDateString(),
            'end' => $period->end->toDateString(),
        ], auth()->user());
    }

    public function render(): View
    {
        $user = auth()->user();
        $definition = $this->definition();
        $filters = $this->currentFilters();
        $rows = $definition->rows($filters);
        $teamView = Visibility::executiveIds($user) === null;

        return view('livewire.reports.index', [
            'reports' => ReportRegistry::all(),
            'definition' => $definition,
            'columns' => $definition->columns(),
            'rows' => array_slice($rows, 0, self::PREVIEW_LIMIT),
            'total' => count($rows),
            'totals' => $definition->totals($rows, $filters),
            'filterLabels' => ReportFilterLabels::describe($filters),
            'exportQuery' => $filters->toQuery(),
            'canExport' => $user->can('reports.export'),
            'available' => $definition->filters(),
            'executives' => $teamView ? Visibility::assignableExecutives($user) : collect(),
            'regions' => $teamView ? Region::query()->orderBy('name')->get() : collect(),
            'cities' => $this->cityOptions(),
            'indicators' => RegionalIndicator::query()->orderBy('name')->get(['id', 'name']),
            'sources' => LeadSource::query()->ordered()->get(),
            'stages' => OpportunityStage::cases(),
        ]);
    }
}
