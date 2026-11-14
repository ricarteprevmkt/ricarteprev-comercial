<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recorte "o executivo só vê o que é dele": quem tem a permissão de ver tudo
 * enxerga todos os registros; os demais, só os do próprio cadastro de executivo.
 *
 * Requer a coluna executive_id no modelo.
 */
trait ScopedToExecutive
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user, string $viewAllAbility = 'funnel.view_all'): Builder
    {
        if ($user->can($viewAllAbility)) {
            return $query;
        }

        $executiveId = $user->executive()->value('id');

        return $query->where($this->qualifyColumn('executive_id'), $executiveId ?? 0);
    }

    public function isOwnedBy(User $user): bool
    {
        $executiveId = $user->executive()->value('id');

        return $executiveId !== null && (int) $this->getAttribute('executive_id') === (int) $executiveId;
    }
}
