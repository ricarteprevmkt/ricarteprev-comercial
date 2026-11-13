<?php

namespace App\Livewire\Admin;

use App\Models\City;
use App\Models\Region;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Regiões comerciais e marcação das cidades atendidas. Só cidades atendidas
 * aparecem nos formulários de executivos, indicadores, leads e visitas.
 */
#[Title('Regiões e cidades')]
class RegionsCities extends Component
{
    use WithPagination;

    #[Url(as: 'uf')]
    public string $uf = 'CE';

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'exibir', except: 'todas')]
    public string $show = 'todas'; // todas|atendidas|sem-regiao

    /** @var list<int|string> */
    public array $selected = [];

    public string $bulkRegion = '';

    public ?int $editingRegionId = null;

    /** @var array{name: string, description: string, active: bool} */
    public array $regionForm = ['name' => '', 'description' => '', 'active' => true];

    public function mount(): void
    {
        $this->authorize('regions.manage');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['uf', 'search', 'show'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function createRegion(): void
    {
        $this->resetValidation();
        $this->editingRegionId = null;
        $this->regionForm = ['name' => '', 'description' => '', 'active' => true];
        Flux::modal('region-form')->show();
    }

    public function editRegion(int $id): void
    {
        $region = Region::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingRegionId = $region->id;
        $this->regionForm = ['name' => $region->name, 'description' => $region->description ?? '', 'active' => $region->active];
        Flux::modal('region-form')->show();
    }

    public function saveRegion(): void
    {
        $this->authorize('regions.manage');
        $this->validate([
            'regionForm.name' => ['required', 'string', 'max:120', Rule::unique('regions', 'name')->ignore($this->editingRegionId)->withoutTrashed()],
            'regionForm.description' => ['nullable', 'string', 'max:255'],
            'regionForm.active' => ['boolean'],
        ], attributes: ['regionForm.name' => 'nome', 'regionForm.description' => 'descrição']);

        $region = $this->editingRegionId ? Region::query()->findOrFail($this->editingRegionId) : new Region;
        $region->fill([
            'name' => $this->regionForm['name'],
            'description' => $this->regionForm['description'] ?: null,
            'active' => $this->regionForm['active'],
        ])->save();

        Flux::modal('region-form')->close();
        Flux::toast(variant: 'success', text: $this->editingRegionId ? 'Região atualizada.' : 'Região criada.');
        unset($this->regions);
    }

    public function toggleServed(int $cityId): void
    {
        $this->authorize('regions.manage');
        $city = City::query()->findOrFail($cityId);
        $city->update(['served' => ! $city->served]);
    }

    public function setCityRegion(int $cityId, string $regionId): void
    {
        $this->authorize('regions.manage');
        $city = City::query()->findOrFail($cityId);
        $region = $regionId === '' ? null : Region::query()->findOrFail((int) $regionId);
        // Ao entrar numa região, a cidade passa a ser atendida.
        $city->update(['region_id' => $region?->id, 'served' => $region ? true : $city->served]);
        unset($this->regions);
    }

    public function applyBulk(string $action): void
    {
        $this->authorize('regions.manage');
        $ids = array_map('intval', $this->selected);

        if ($ids === []) {
            Flux::toast(variant: 'warning', text: 'Selecione ao menos uma cidade.');

            return;
        }

        DB::transaction(function () use ($ids, $action) {
            $cities = City::query()->whereKey($ids)->get();

            foreach ($cities as $city) {
                match ($action) {
                    'serve' => $city->update(['served' => true]),
                    'unserve' => $city->update(['served' => false]),
                    'region' => $city->update([
                        'region_id' => $this->bulkRegion !== '' ? (int) $this->bulkRegion : null,
                        'served' => $this->bulkRegion !== '' ? true : $city->served,
                    ]),
                    default => null,
                };
            }
        });

        Flux::toast(variant: 'success', text: count($ids).' cidade(s) atualizada(s).');
        $this->selected = [];
        unset($this->regions);
    }

    /** @return Collection<int, Region> */
    #[Computed]
    public function regions(): Collection
    {
        return Region::query()
            ->withCount(['cities', 'cities as served_cities_count' => fn ($q) => $q->where('served', true)])
            ->orderByDesc('active')->orderBy('name')->get();
    }

    /** @return LengthAwarePaginator<int, City> */
    #[Computed]
    public function cities(): LengthAwarePaginator
    {
        return City::query()
            ->with('region')
            ->where('uf', $this->uf)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->show === 'atendidas', fn ($q) => $q->where('served', true))
            ->when($this->show === 'sem-regiao', fn ($q) => $q->where('served', true)->whereNull('region_id'))
            ->orderByDesc('served')->orderBy('name')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.admin.regions-cities', [
            'ufs' => City::query()->distinct()->orderBy('uf')->pluck('uf'),
            'servedTotal' => City::query()->where('served', true)->count(),
        ]);
    }
}
