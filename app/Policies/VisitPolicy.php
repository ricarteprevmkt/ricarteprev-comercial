<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

/**
 * "agenda.view_all" vê a agenda de todos; "agenda.view_own", só a própria.
 * Alterar exige também "agenda.manage".
 */
class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('agenda.view_all') || $user->can('agenda.view_own');
    }

    public function view(User $user, Visit $visit): bool
    {
        return $user->can('agenda.view_all') || ($user->can('agenda.view_own') && $visit->isOwnedBy($user));
    }

    public function update(User $user, Visit $visit): bool
    {
        return $user->can('agenda.manage') && $this->view($user, $visit);
    }
}
