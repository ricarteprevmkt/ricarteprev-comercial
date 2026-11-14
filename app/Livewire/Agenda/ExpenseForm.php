<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\SaveExpense;
use App\Livewire\Concerns\LoadsLocationOptions;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Executive;
use App\Models\Visit;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Lançamento de despesa com comprovante (foto ou PDF). Toda despesa nasce
 * "aguardando aprovação".
 */
class ExpenseForm extends Component
{
    use LoadsLocationOptions, WithFileUploads;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var TemporaryUploadedFile|null */
    public $receipt = null;

    public function mount(): void
    {
        $this->resetFields();
    }

    #[On('expense-form:create')]
    public function create(?string $date = null, ?int $visitId = null): void
    {
        abort_unless(auth()->user()->can('expenses.manage'), 403);
        $this->resetFields($date);

        if ($visitId) {
            $visit = Visit::query()->findOrFail($visitId);
            $this->authorize('view', $visit);
            $this->form['visit_id'] = $visit->id;
            $this->form['executive_id'] = $visit->executive_id;
            $this->form['city_id'] = $visit->city_id;
            $this->form['spent_on'] = $visit->scheduled_at->toDateString();
        }

        Flux::modal('expense-form')->show();
    }

    public function save(SaveExpense $action): void
    {
        $user = auth()->user();
        abort_unless($user->can('expenses.manage'), 403);
        $allowed = $this->executives()->pluck('id')->all();

        $this->validate([
            'form.description' => ['required', 'string', 'max:255'],
            'form.expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'form.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999'],
            'form.spent_on' => ['required', 'date', 'before_or_equal:today'],
            'form.executive_id' => [$user->can('finance.view') ? 'nullable' : 'required', 'integer', 'in:'.implode(',', $allowed ?: [0])],
            'form.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'form.visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], [], [
            'form.description' => 'descrição', 'form.expense_category_id' => 'categoria', 'form.amount' => 'valor',
            'form.spent_on' => 'data', 'form.executive_id' => 'executivo', 'receipt' => 'comprovante',
        ]);

        $action->handle(null, [
            'description' => $this->form['description'],
            'expense_category_id' => (int) $this->form['expense_category_id'],
            'amount' => (string) $this->form['amount'],
            'spent_on' => $this->form['spent_on'],
            'executive_id' => $this->form['executive_id'] ? (int) $this->form['executive_id'] : null,
            'city_id' => $this->form['city_id'] ? (int) $this->form['city_id'] : null,
            'visit_id' => $this->form['visit_id'] ? (int) $this->form['visit_id'] : null,
        ], $this->receipt, $user);

        Flux::modal('expense-form')->close();
        Flux::toast(variant: 'success', text: 'Despesa lançada. Ela fica aguardando aprovação.');
        $this->dispatch('expense-saved');
        $this->resetFields();
    }

    private function resetFields(?string $date = null): void
    {
        $this->resetValidation();
        $this->receipt = null;
        $this->form = [
            'description' => '', 'expense_category_id' => null, 'amount' => '',
            'spent_on' => $date ?: now()->toDateString(),
            'executive_id' => auth()->user()?->executive()->value('id'),
            'city_id' => null, 'visit_id' => null,
        ];
    }

    /** @return Collection<int, Executive> */
    private function executives(): Collection
    {
        $user = auth()->user();

        return $user->can('finance.view')
            ? Visibility::assignableExecutives($user, $this->form['executive_id'] ? (int) $this->form['executive_id'] : null)
            : Executive::query()->with('user')->where('user_id', $user->id)->get();
    }

    public function render(): View
    {
        return view('livewire.agenda.expense-form', [
            'categories' => ExpenseCategory::query()->active()->ordered()->get(),
            'executives' => $this->executives(),
            'cities' => $this->cityOptions($this->form['city_id'] ? (int) $this->form['city_id'] : null),
        ]);
    }
}
