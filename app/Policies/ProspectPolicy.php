<?php

namespace App\Policies;

use App\Enums\ProspectStatus;
use App\Models\Prospect;
use App\Models\User;

/**
 * Quem tem "prospects.view_all" vê todos os interessados; o executivo, só os
 * atribuídos a ele. Importar e distribuir exige "prospects.manage"; o
 * executivo pode qualificar e descartar os seus.
 */
class ProspectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prospects.view_all') || $user->can('prospects.view_own');
    }

    public function view(User $user, Prospect $prospect): bool
    {
        if ($user->can('prospects.view_all')) {
            return true;
        }

        return $user->can('prospects.view_own') && $prospect->executive_id !== null
            && $prospect->executive_id === $user->executive()->value('id');
    }

    public function qualify(User $user, Prospect $prospect): bool
    {
        return $prospect->status === ProspectStatus::New && $user->can('leads.manage') && $this->view($user, $prospect);
    }

    public function discard(User $user, Prospect $prospect): bool
    {
        return $prospect->status === ProspectStatus::New
            && ($user->can('prospects.manage') || $this->view($user, $prospect));
    }
}
