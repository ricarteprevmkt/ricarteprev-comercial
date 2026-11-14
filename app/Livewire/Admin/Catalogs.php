<?php

namespace App\Livewire\Admin;

use App\Enums\CatalogType;
use App\Models\Catalogs\CatalogItem;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Itens dos catálogos configuráveis. Itens não são excluídos, só desativados,
 * para preservar o histórico dos registros que já os usam.
 */
#[Title('Catálogos')]
class Catalogs extends Component
{
    #[Url(as: 'catalogo')]
    public string $type = 'origens';

    public ?int $editingId = null;

    /** @var array{name: string, sort_order: int|string, active: bool, nature: string, applies_to: string, is_travel: bool} */
    public array $form = ['name' => '', 'sort_order' => 0, 'active' => true, 'nature' => 'variable', 'applies_to' => 'both', 'is_travel' => false];

    public function mount(): void
    {
        $this->authorize('settings.manage');

        if (CatalogType::tryFrom($this->type) === null) {
            $this->type = CatalogType::LeadSources->value;
        }
    }

    public function catalog(): CatalogType
    {
        return CatalogType::from($this->type);
    }

    public function updatedType(): void
    {
        if (CatalogType::tryFrom($this->type) === null) {
            $this->type = CatalogType::LeadSources->value;
        }
        unset($this->items);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $next = (int) ($this->catalog()->model()::query()->max('sort_order') ?? 0) + 10;
        $this->form = ['name' => '', 'sort_order' => $next, 'active' => true, 'nature' => 'variable', 'applies_to' => 'both', 'is_travel' => false];
        Flux::modal('catalog-form')->show();
    }

    public function edit(int $id): void
    {
        $item = $this->catalog()->model()::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $item->id;
        $this->form = [
            'name' => $item->name,
            'sort_order' => $item->sort_order,
            'active' => $item->active,
            'nature' => $item->getAttribute('nature') ?? 'variable',
            'applies_to' => $item->getAttribute('applies_to') ?? 'both',
            'is_travel' => (bool) ($item->getAttribute('is_travel') ?? false),
        ];
        Flux::modal('catalog-form')->show();
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $catalog = $this->catalog();
        $model = $catalog->model();
        $table = (new $model)->getTable();

        $this->validate([
            'form.name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->ignore($this->editingId)],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:65000'],
            'form.active' => ['boolean'],
            'form.nature' => ['required', Rule::in(['variable', 'fixed'])],
            'form.applies_to' => ['required', Rule::in(['opportunity', 'referral', 'both'])],
        ], attributes: ['form.name' => 'nome', 'form.sort_order' => 'ordem']);

        /** @var CatalogItem $item */
        $item = $this->editingId ? $model::query()->findOrFail($this->editingId) : new $model;
        $item->fill([
            'name' => $this->form['name'],
            'sort_order' => (int) $this->form['sort_order'],
            'active' => $this->form['active'],
        ]);

        if ($catalog === CatalogType::ExpenseCategories) {
            $item->setAttribute('nature', $this->form['nature']);
            $item->setAttribute('is_travel', (bool) $this->form['is_travel']);
        }
        if ($catalog === CatalogType::LossReasons) {
            $item->setAttribute('applies_to', $this->form['applies_to']);
        }

        $item->save();

        Flux::modal('catalog-form')->close();
        Flux::toast(variant: 'success', text: 'Item salvo.');
        unset($this->items);
    }

    public function toggle(int $id): void
    {
        $this->authorize('settings.manage');
        $item = $this->catalog()->model()::query()->findOrFail($id);
        $item->update(['active' => ! $item->active]);
        unset($this->items);
    }

    /** @return Collection<int, CatalogItem> */
    #[Computed]
    public function items(): Collection
    {
        return $this->catalog()->model()::query()->ordered()->get();
    }

    public function render(): View
    {
        return view('livewire.admin.catalogs', [
            'catalogs' => CatalogType::cases(),
            'current' => $this->catalog(),
        ]);
    }
}
