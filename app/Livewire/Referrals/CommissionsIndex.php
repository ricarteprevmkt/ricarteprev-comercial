<?php

namespace App\Livewire\Referrals;

use App\Actions\Referrals\Commissions;
use App\Enums\CommissionStatus;
use App\Models\Commission;
use App\Models\RegionalIndicator;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Comissões')]
class CommissionsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'situacao', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'parceiro', except: '')]
    public string $indicatorFilter = '';

    public ?int $selectedId = null;

    /** @var array{amount: string, note: string} */
    public array $approve = ['amount' => '', 'note' => ''];

    /** @var array{amount: string, paid_at: string, note: string} */
    public array $payment = ['amount' => '', 'paid_at' => '', 'note' => ''];

    /** @var array{reason: string} */
    public array $cancel = ['reason' => ''];

    public function mount(): void
    {
        $this->authorize('commissions.manage');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['statusFilter', 'indicatorFilter'], true)) {
            $this->resetPage();
        }
    }

    public function open(string $modal, int $id): void
    {
        $this->authorize('commissions.manage');
        $commission = Commission::query()->findOrFail($id);
        $this->resetValidation();
        $this->selectedId = $commission->id;
        $this->approve = ['amount' => (string) $commission->expected_amount, 'note' => ''];
        $remaining = $commission->approved_amount ? Money::sub($commission->approved_amount, $commission->paidTotal()) : '';
        $this->payment = ['amount' => $remaining, 'paid_at' => now()->toDateString(), 'note' => ''];
        $this->cancel = ['reason' => ''];
        Flux::modal("commission-{$modal}")->show();
    }

    public function saveApprove(Commissions $action): void
    {
        $this->authorize('commissions.manage');
        $this->validate(['approve.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999'], 'approve.note' => ['nullable', 'string', 'max:500']],
            attributes: ['approve.amount' => 'valor aprovado']);

        $this->run(fn () => $action->approve($this->selected(), (string) $this->approve['amount'], auth()->user(), $this->approve['note'] ?: null), 'approve', 'Comissão aprovada.');
    }

    public function savePayment(Commissions $action): void
    {
        $this->authorize('commissions.manage');
        $this->validate([
            'payment.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999'],
            'payment.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment.note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['payment.amount' => 'valor', 'payment.paid_at' => 'data do pagamento']);

        $this->run(fn () => $action->pay($this->selected(), (string) $this->payment['amount'], $this->payment['paid_at'], auth()->user(), $this->payment['note'] ?: null), 'payment', 'Pagamento registrado.');
    }

    public function saveCancel(Commissions $action): void
    {
        $this->authorize('commissions.manage');
        $this->validate(['cancel.reason' => ['required', 'string', 'max:500']], attributes: ['cancel.reason' => 'motivo']);

        $this->run(fn () => $action->cancel($this->selected(), $this->cancel['reason']), 'cancel', 'Comissão cancelada.');
    }

    private function run(callable $op, string $modal, string $message): void
    {
        try {
            $op();
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal("commission-{$modal}")->close();
        Flux::toast(variant: 'success', text: $message);
        unset($this->commissions, $this->totals);
    }

    private function selected(): Commission
    {
        return Commission::query()->findOrFail((int) $this->selectedId);
    }

    /** @return array<string, string> status => soma */
    #[Computed]
    public function totals(): array
    {
        $rows = Commission::query()
            ->when($this->indicatorFilter !== '', fn ($q) => $q->where('regional_indicator_id', $this->indicatorFilter))
            ->selectRaw('status, sum(coalesce(approved_amount, expected_amount)) as total')
            ->groupBy('status')->pluck('total', 'status');

        return collect(CommissionStatus::cases())->mapWithKeys(fn ($s) => [$s->value => number_format((float) ($rows[$s->value] ?? 0), 2, '.', '')])->all();
    }

    /** @return LengthAwarePaginator<int, Commission> */
    #[Computed]
    public function commissions(): LengthAwarePaginator
    {
        return Commission::query()
            ->with(['indicator', 'contract.lead'])
            ->withSum('payments as paid_total', 'amount')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->indicatorFilter !== '', fn ($q) => $q->where('regional_indicator_id', $this->indicatorFilter))
            ->orderByRaw("field(status, 'expected', 'approved', 'paid', 'cancelled')")
            ->latest('generated_at')->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.referrals.commissions-index', [
            'statuses' => CommissionStatus::cases(),
            'indicators' => RegionalIndicator::query()->orderBy('name')->get(['id', 'name']),
            'current' => $this->selectedId ? Commission::query()->with(['indicator', 'contract.lead', 'payments'])->find($this->selectedId) : null,
        ]);
    }
}
