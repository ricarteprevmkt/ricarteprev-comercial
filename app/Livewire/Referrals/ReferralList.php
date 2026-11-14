<?php

namespace App\Livewire\Referrals;

use App\Actions\Referrals\SyncReferralStatus;
use App\Enums\ReferralStatus;
use App\Models\Referral;
use App\Models\Region;
use App\Models\RegionalIndicator;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista de indicações para a equipe (todas, ou as do executivo).
 */
#[Title('Indicações')]
class ReferralList extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'situacao', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'indicador', except: '')]
    public string $indicatorFilter = '';

    #[Url(as: 'regiao', except: '')]
    public string $regionFilter = '';

    #[Url(as: 'duplicadas', except: false)]
    public bool $showDuplicates = false;

    public ?int $selectedId = null;

    /** @var array{status: string, note: string} */
    public array $change = ['status' => '', 'note' => ''];

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['referrals.view_all', 'referrals.create']), 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'indicatorFilter', 'regionFilter', 'showDuplicates'], true)) {
            $this->resetPage();
        }
    }

    #[On('referral-saved')]
    public function refreshList(): void
    {
        unset($this->referrals);
    }

    private function find(int $id): Referral
    {
        return Referral::query()->visibleTo(auth()->user())->findOrFail($id);
    }

    public function openChange(int $id, string $status): void
    {
        abort_unless(auth()->user()->can('leads.manage'), 403);
        $this->find($id);
        $this->resetValidation();
        $this->selectedId = $id;
        $this->change = ['status' => $status, 'note' => ''];
        Flux::modal('referral-status')->show();
    }

    public function saveChange(SyncReferralStatus $sync): void
    {
        abort_unless(auth()->user()->can('leads.manage'), 403);
        $referral = $this->find((int) $this->selectedId);

        $this->validate([
            'change.status' => ['required', Rule::in(['contacted', 'qualified', 'lost', 'referred'])],
            'change.note' => [$this->change['status'] === 'lost' ? 'required' : 'nullable', 'string', 'max:500'],
        ], attributes: ['change.note' => 'motivo']);

        try {
            $sync->manual($referral, ReferralStatus::from($this->change['status']), auth()->user(), $this->change['note'] ?: null);
        } catch (ValidationException $e) {
            $this->addError('change.status', (string) collect($e->errors())->flatten()->first());

            return;
        }

        Flux::modal('referral-status')->close();
        Flux::toast(variant: 'success', text: 'Situação da indicação atualizada.');
        unset($this->referrals);
    }

    /** @return LengthAwarePaginator<int, Referral> */
    #[Computed]
    public function referrals(): LengthAwarePaginator
    {
        return Referral::query()
            ->visibleTo(auth()->user())
            ->with(['lead.city', 'indicator', 'executive.user', 'channel'])
            ->join('leads', 'leads.id', '=', 'referrals.lead_id')
            ->select('referrals.*')
            ->where('referrals.is_duplicate', $this->showDuplicates)
            ->when($this->search !== '', fn ($q) => $q->where('leads.name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('referrals.status', $this->statusFilter))
            ->when($this->indicatorFilter !== '', fn ($q) => $q->where('referrals.regional_indicator_id', $this->indicatorFilter))
            ->when($this->regionFilter !== '', fn ($q) => $q->whereIn('referrals.regional_indicator_id', RegionalIndicator::query()->where('region_id', $this->regionFilter)->select('id')))
            ->latest('referrals.referred_at')->latest('referrals.id')
            ->paginate(20);
    }

    public function render(): View
    {
        $all = auth()->user()->can('referrals.view_all');

        return view('livewire.referrals.referral-list', [
            'statuses' => ReferralStatus::cases(),
            'indicators' => $all ? RegionalIndicator::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'regions' => $all ? Region::query()->active()->orderBy('name')->get() : collect(),
        ]);
    }
}
