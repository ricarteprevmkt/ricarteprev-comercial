<?php

namespace App\Livewire\Funnel;

use App\Actions\Crm\RegisterContract;
use App\Enums\ContractStatus;
use App\Enums\InssOutcome;
use App\Models\Contract;
use App\Models\SystemSetting;
use App\Services\C2\C2Client;
use App\Support\Period;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Contratos e recebimentos. Valor contratado e receita recebida aparecem
 * sempre separados.
 */
#[Title('Contratos')]
class Contracts extends Component
{
    use WithPagination;

    #[Url(as: 'situacao', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    /** Resultado no INSS (C2): '', sem-c2 ou um InssOutcome. */
    #[Url(as: 'inss', except: '')]
    public string $inssFilter = '';

    public ?int $selectedId = null;

    public string $signedAt = '';

    /** @var array{received_at: string, amount: string, notes: string} */
    public array $receipt = ['received_at' => '', 'amount' => '', 'notes' => ''];

    public function mount(): void
    {
        $this->authorize('viewAny', Contract::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['statusFilter', 'search', 'inssFilter'], true)) {
            $this->resetPage();
        }
    }

    #[On('period-changed')]
    public function periodChanged(): void
    {
        unset($this->totals);
    }

    public function openSign(int $id): void
    {
        $contract = $this->find($id);
        $this->authorize('update', $contract);
        $this->resetValidation();
        $this->selectedId = $contract->id;
        $this->signedAt = now()->toDateString();
        Flux::modal('sign-contract')->show();
    }

    public function sign(RegisterContract $action): void
    {
        $contract = $this->find((int) $this->selectedId);
        $this->authorize('update', $contract);
        $this->validate(['signedAt' => ['required', 'date', 'before_or_equal:today']], attributes: ['signedAt' => 'data de assinatura']);

        try {
            $action->sign($contract, $this->signedAt, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal('sign-contract')->close();
        Flux::toast(variant: 'success', text: 'Contrato assinado. A oportunidade foi atualizada.');
        unset($this->contracts, $this->totals);
    }

    public function openReceipts(int $id): void
    {
        $contract = $this->find($id);
        $this->authorize('view', $contract);
        $this->resetValidation();
        $this->selectedId = $contract->id;
        $this->receipt = ['received_at' => now()->toDateString(), 'amount' => '', 'notes' => ''];
        Flux::modal('receipts')->show();
    }

    public function saveReceipt(RegisterContract $action): void
    {
        $contract = $this->find((int) $this->selectedId);
        $this->authorize('update', $contract);
        $this->validate([
            'receipt.received_at' => ['required', 'date', 'before_or_equal:today'],
            'receipt.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'receipt.notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['receipt.received_at' => 'data', 'receipt.amount' => 'valor']);

        try {
            $action->registerReceipt($contract, [
                'received_at' => $this->receipt['received_at'],
                'amount' => $this->receipt['amount'],
                'notes' => $this->receipt['notes'] ?: null,
            ], auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->receipt = ['received_at' => now()->toDateString(), 'amount' => '', 'notes' => ''];
        Flux::toast(variant: 'success', text: 'Recebimento registrado.');
        unset($this->contracts, $this->totals);
    }

    private function find(int $id): Contract
    {
        return Contract::query()->visibleTo(auth()->user())->findOrFail($id);
    }

    #[Computed]
    public function selected(): ?Contract
    {
        return $this->selectedId ? Contract::query()->with(['lead', 'receipts'])->find($this->selectedId) : null;
    }

    /**
     * Totais do período: contratado (assinados no período) e recebido (recebimentos no período).
     *
     * @return array{signed: int, contracted: string, received: string, pending: int}
     */
    #[Computed]
    public function totals(): array
    {
        $period = Period::current();
        $dates = [$period->start->toDateString(), $period->end->toDateString()];
        $base = fn () => Contract::query()->visibleTo(auth()->user());

        return [
            'signed' => $base()->where('status', ContractStatus::Signed->value)->whereBetween('signed_at', $dates)->count(),
            'contracted' => (string) ($base()->where('status', ContractStatus::Signed->value)->whereBetween('signed_at', $dates)->sum('contract_value') ?: '0'),
            'received' => (string) ($base()->join('contract_receipts', 'contract_receipts.contract_id', '=', 'contracts.id')->whereBetween('contract_receipts.received_at', $dates)->sum('contract_receipts.amount') ?: '0'),
            'pending' => $base()->where('status', ContractStatus::Pending->value)->count(),
        ];
    }

    /** @return LengthAwarePaginator<int, Contract> */
    #[Computed]
    public function contracts(): LengthAwarePaginator
    {
        return Contract::query()
            ->visibleTo(auth()->user())
            ->with(['lead', 'executive.user', 'source', 'indicator', 'c2Processes'])
            ->join('leads', 'leads.id', '=', 'contracts.lead_id')
            ->select('contracts.*')
            // withSum depois do select, senão o select descarta a soma.
            ->withSum('receipts as received_total', 'amount')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('contracts.status', $this->statusFilter))
            ->when($this->inssFilter === 'sem-c2', fn ($q) => $q->missingInC2($this->c2Days()))
            ->when(InssOutcome::tryFrom($this->inssFilter), fn ($q, InssOutcome $outcome) => $q->whereExists(fn ($e) => $e
                ->selectRaw('1')->from('c2_processes')->whereColumn('c2_processes.lead_id', 'contracts.lead_id')
                ->where('c2_processes.in_scope', true)->where('c2_processes.outcome', $outcome->value)))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('leads.name', 'like', "%{$this->search}%")
                ->orWhere('contracts.number', 'like', "%{$this->search}%")))
            ->orderByRaw("contracts.status = 'pending' desc")
            ->latest('contracts.signed_at')->latest('contracts.id')
            ->paginate(20);
    }

    private function c2Days(): int
    {
        return (int) SystemSetting::get('c2.registration_days', 2);
    }

    public function render(): View
    {
        return view('livewire.funnel.contracts', [
            'c2On' => C2Client::enabled(),
            'c2Days' => $this->c2Days(),
            'outcomes' => InssOutcome::cases(),
            'statuses' => ContractStatus::cases(),
            'periodLabel' => Period::current()->label(),
            'scopeAll' => Visibility::executiveIds(auth()->user()) === null,
        ]);
    }
}
