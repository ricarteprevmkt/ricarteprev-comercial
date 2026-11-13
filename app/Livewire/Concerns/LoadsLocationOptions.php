<?php

namespace App\Livewire\Concerns;

use App\Models\City;
use App\Models\Region;
use Illuminate\Support\Collection;

/**
 * Opções de cidade e região para formulários: só cidades marcadas como
 * atendidas (mais a já selecionada, se deixou de ser atendida).
 */
trait LoadsLocationOptions
{
    /** @return Collection<int, City> */
    protected function cityOptions(?int $selected = null): Collection
    {
        return City::query()
            ->where(fn ($q) => $q->where('served', true)->when($selected, fn ($q) => $q->orWhere('id', $selected)))
            ->orderBy('name')
            ->get(['id', 'name', 'uf', 'region_id']);
    }

    /** @return Collection<int, Region> */
    protected function regionOptions(?int $selected = null): Collection
    {
        return Region::query()
            ->where(fn ($q) => $q->where('active', true)->when($selected, fn ($q) => $q->orWhere('id', $selected)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Ao escolher a cidade, sugere a região dela. */
    protected function regionForCity(?int $cityId): ?int
    {
        return $cityId ? City::query()->whereKey($cityId)->value('region_id') : null;
    }
}
