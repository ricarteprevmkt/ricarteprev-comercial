<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

/**
 * Quem tem "funnel.view_all" vê todos os registros; os demais, só os do
 * próprio cadastro de executivo. Alterar exige também "contracts.manage".
 */
class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('funnel.view_all') || $user->can('funnel.view_own');
    }

    public function view(User $user, Contract $model): bool
    {
        return $user->can('funnel.view_all') || ($user->can('funnel.view_own') && $model->isOwnedBy($user));
    }

    public function update(User $user, Contract $model): bool
    {
        return $user->can('contracts.manage') && $this->view($user, $model);
    }
}
