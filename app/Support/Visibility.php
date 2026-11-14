<?php

namespace App\Support;

use App\Enums\ExecutiveStatus;
use App\Models\Executive;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Recorte de dados por perfil para o funil: quem vê a equipe inteira e quem
 * vê só os próprios registros.
 */
final class Visibility
{
    /**
     * Executivos cujos registros o usuário pode ver. null = todos.
     *
     * @return list<int>|null
     */
    public static function executiveIds(User $user): ?array
    {
        if ($user->can('funnel.view_all')) {
            return null;
        }

        $id = $user->executive()->value('id');

        return $id === null ? [] : [(int) $id];
    }

    /**
     * Executivos que o usuário pode escolher como responsável num formulário.
     *
     * @return Collection<int, Executive>
     */
    public static function assignableExecutives(User $user, ?int $include = null): Collection
    {
        $query = Executive::query()->with('user')
            ->join('users', 'users.id', '=', 'executives.user_id')
            ->select('executives.*')
            ->orderBy('users.name');

        if ($user->can('funnel.view_all')) {
            return $query->where(fn ($q) => $q
                ->where('executives.status', '!=', ExecutiveStatus::Terminated->value)
                ->when($include, fn ($q) => $q->orWhere('executives.id', $include)))
                ->get();
        }

        return $query->where('executives.user_id', $user->id)->get();
    }
}
