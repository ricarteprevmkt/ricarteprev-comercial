<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\ReviewExpense;
use App\Enums\ExpenseKind;
use App\Enums\ExpenseStatus;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Expense;
use App\Support\Money;
use App\Support\Period;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Despesas: o executivo vê e lança as próprias; quem tem "finance.view" vê
 * todas; quem tem "expenses.approve" aprova ou recusa.
 */
#[Title('Despesas')]
class Expenses extends Component
{
    use WithPagination;

    #[Url(as: 'situacao', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'categoria', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'executivo', except: '')]
    public string $executiveFilter = '';

    /** @var list<int|string> */
    public array $selected = [];

    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['finance.view', 'expenses.view_own']), 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['statusFilter', 'categoryFilter', 'executiveFilter'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    #[On('expense-saved')]
    #[On('period-changed')]
    public function refreshList(): void
    {
        unset($this->expenses, $this->totals);
    }

    public function approve(int $id, ReviewExpense $review): void
    {
        $this->review(fn () => $review->approve($this->find($id), auth()->user()), 'Despesa aprovada.');
    }

    public function approveSelected(ReviewExpense $review): void
    {
        abort_unless(auth()->user()->can('expenses.approve'), 403);
        $ids = array_map('intval', $this->selected);
        $visible = Expense::query()->visibleTo(auth()->user(), 'finance.view')->whereKey($ids)->get(['id'])
            ->map(fn (Expense $e): int => $e->id)->values()->all();
        $count = $review->approveMany($visible, auth()->user());

        $this->selected = [];
        Flux::toast(variant: 'success', text: "{$count} despesa(s) aprovada(s).");
        $this->refreshList();
    }

    public function openReject(int $id): void
    {
        $this->authorize('approve', $this->find($id));
        $this->resetValidation();
        $this->rejectingId = $id;
        $this->rejectionReason = '';
        Flux::modal('expense-reject')->show();
    }

    public function reject(ReviewExpense $review): void
    {
        $this->validate(['rejectionReason' => ['required', 'string', 'max:255']], attributes: ['rejectionReason' => 'motivo']);
        $this->review(fn () => $review->reject($this->find((int) $this->rejectingId), auth()->user(), $this->rejectionReason), 'Despesa recusada. O executivo foi avisado.');
        Flux::modal('expense-reject')->close();
    }

    private function review(callable $op, string $message): void
    {
        try {
            $op();
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: (string) collect($e->errors())->flatten()->first());

            return;
        }

        Flux::toast(variant: 'success', text: $message);
        $this->refreshList();
    }

    private function find(int $id): Expense
    {
        return Expense::query()->visibleTo(auth()->user(), 'finance.view')->findOrFail($id);
    }

    /** @return array{pending: string, approved: string, rejected: string} */
    #[Computed]
    public function totals(): array
    {
        $rows = $this->query(withStatus: false)->selectRaw('expenses.status, sum(expenses.amount) as total')->groupBy('expenses.status')->pluck('total', 'status');

        return [
            'pending' => Money::of($rows['pending'] ?? 0),
            'approved' => Money::of($rows['approved'] ?? 0),
            'rejected' => Money::of($rows['rejected'] ?? 0),
        ];
    }

    /** @return Builder<Expense> */
    private function query(bool $withStatus = true): Builder
    {
        $period = Period::current();

        return Expense::query()
            ->visibleTo(auth()->user(), 'finance.view')
            ->where('expenses.kind', ExpenseKind::Actual->value)
            ->whereBetween('expenses.spent_on', [$period->start->toDateString(), $period->end->toDateString()])
            ->when($withStatus && $this->statusFilter !== '', fn ($q) => $q->where('expenses.status', $this->statusFilter))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('expenses.expense_category_id', $this->categoryFilter))
            ->when($this->executiveFilter !== '', fn ($q) => $this->executiveFilter === 'geral'
                ? $q->whereNull('expenses.executive_id')
                : $q->where('expenses.executive_id', $this->executiveFilter));
    }

    /** @return LengthAwarePaginator<int, Expense> */
    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        return $this->query()
            ->with(['category', 'executive.user', 'attachments', 'visit.lead'])
            ->orderByRaw("expenses.status = 'pending' desc")
            ->latest('expenses.spent_on')->latest('expenses.id')
            ->paginate(25);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.finance.expenses', [
            'statuses' => ExpenseStatus::cases(),
            'categories' => ExpenseCategory::query()->ordered()->get(),
            'executives' => $user->can('finance.view') ? Visibility::assignableExecutives($user) : collect(),
            'canApprove' => $user->can('expenses.approve'),
            'periodLabel' => Period::current()->label(),
        ]);
    }
}
