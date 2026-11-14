<?php

namespace App\Notifications;

use App\Models\Expense;
use App\Support\Money;
use Illuminate\Notifications\Notification;

class ExpenseRejected extends Notification
{
    public function __construct(private Expense $expense) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expense_rejected',
            'message' => 'Despesa recusada: '.$this->expense->description.' ('.Money::format($this->expense->amount).'). Motivo: '.$this->expense->rejection_reason,
            'url' => route('finance.expenses', ['situacao' => 'rejected']),
            'expense_id' => $this->expense->id,
        ];
    }
}
