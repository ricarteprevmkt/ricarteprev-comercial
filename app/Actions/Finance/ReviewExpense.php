<?php

namespace App\Actions\Finance;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseRejected;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Aprova ou recusa despesas. Só despesas aguardando aprovação; ninguém aprova
 * a própria despesa (ExpensePolicy::approve). Recusar exige motivo e avisa
 * o executivo.
 */
final class ReviewExpense
{
    public function approve(Expense $expense, User $reviewer): Expense
    {
        $this->guard($expense, $reviewer);

        $expense->forceFill([
            'status' => ExpenseStatus::Approved,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return $expense;
    }

    public function reject(Expense $expense, User $reviewer, string $reason): Expense
    {
        $this->guard($expense, $reviewer);

        $expense->forceFill([
            'status' => ExpenseStatus::Rejected,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $expense->executive?->user->notify(new ExpenseRejected($expense));

        return $expense;
    }

    /**
     * @param  array<int, int>  $ids
     * @return int quantas foram aprovadas
     */
    public function approveMany(array $ids, User $reviewer): int
    {
        $count = 0;

        foreach (Expense::query()->whereKey($ids)->where('status', ExpenseStatus::Pending->value)->get() as $expense) {
            if (Gate::forUser($reviewer)->allows('approve', $expense)) {
                $this->approve($expense, $reviewer);
                $count++;
            }
        }

        return $count;
    }

    private function guard(Expense $expense, User $reviewer): void
    {
        if ($expense->status !== ExpenseStatus::Pending) {
            throw ValidationException::withMessages(['review' => 'Esta despesa já foi analisada.']);
        }

        if (Gate::forUser($reviewer)->denies('approve', $expense)) {
            throw ValidationException::withMessages(['review' => 'Você não pode analisar esta despesa.']);
        }
    }
}
