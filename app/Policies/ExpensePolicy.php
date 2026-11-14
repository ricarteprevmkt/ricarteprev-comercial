<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * "finance.view" vê todas as despesas; "expenses.view_own", só as próprias.
 * Lançar e alterar exige "expenses.manage"; aprovar, "expenses.approve".
 */
class ExpensePolicy
{
    public function view(User $user, Expense $expense): bool
    {
        return $user->can('finance.view') || ($user->can('expenses.view_own') && $expense->isOwnedBy($user));
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('expenses.manage') && $this->view($user, $expense);
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $user->can('expenses.approve') && ! $expense->isOwnedBy($user);
    }
}
