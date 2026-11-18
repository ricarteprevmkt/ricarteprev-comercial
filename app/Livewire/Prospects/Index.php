<?php

namespace App\Livewire\Prospects;

use App\Actions\Prospects\ImportProspects;
use App\Enums\ProspectStatus;
use App\Metrics\FunnelMetrics;
use App\Models\Prospect;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Interessados que chegam pelo WhatsApp (planilha exportada do C2). Ficam
 * antes do funil: são distribuídos aos executivos e, quando qualificados,
 * viram contato e oportunidade.
 */
#[Title('Interessados')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public const PERIODS = ['7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', '90' => 'Últimos 90 dias', '' => 'Desde o início'];

    #[Url(as: 'situacao')]
    public string $status = 'new';

    #[Url(as: 'executivo', except: '')]
    public string $executive = '';

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'entrada', except: '')]
    public string $period = '';

    #[Url(as: 'demanda', except: false)]
    public bool $withDemand = false;

    /** @var list<int> */
    public array $selected = [];

    public string $assignTo = '';

    /** @var UploadedFile|null */
    public $file = null;

    public ?int $discardId = null;

    public string $discardReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Prospect::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'executive', 'search', 'period', 'withDemand'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    /** @return LengthAwarePaginator<int, Prospect> */
    #[Computed]
    public function prospects(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $digits = preg_replace('/\D/', '', $search) ?? '';

        return Prospect::query()
            ->visibleTo(auth()->user())
            ->with(['executive.user', 'lead'])
            ->when(ProspectStatus::tryFrom($this->status), fn ($q, ProspectStatus $s) => $q->where('status', $s->value))
            ->when($this->executive === 'nenhum', fn ($q) => $q->whereNull('executive_id'))
            ->when(ctype_digit($this->executive), fn ($q) => $q->where('executive_id', (int) $this->executive))
            ->when($this->period !== '', fn ($q) => $q->where('entered_at', '>=', today()->subDays((int) $this->period)->toDateString()))
            ->when($this->withDemand, fn ($q) => $q->whereNotNull('demand'))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%"))))
            ->latest('entered_at')->latest('id')
            ->paginate(30);
    }

    /** @return array{new: int, unassigned: int, week: int, qualified: int, discarded: int, rate: float|null} */
    #[Computed]
    public function stats(): array
    {
        $base = fn () => Prospect::query()->visibleTo(auth()->user());
        $qualified = $base()->where('status', ProspectStatus::Qualified->value)->count();
        $discarded = $base()->where('status', ProspectStatus::Discarded->value)->count();

        return [
            'new' => $base()->where('status', ProspectStatus::New->value)->count(),
            'unassigned' => $base()->where('status', ProspectStatus::New->value)->whereNull('executive_id')->count(),
            'week' => $base()->where('entered_at', '>=', today()->subDays(7)->toDateString())->count(),
            'qualified' => $qualified,
            'discarded' => $discarded,
            'rate' => FunnelMetrics::pct($qualified, $qualified + $discarded),
        ];
    }

    public function import(ImportProspects $action): void
    {
        abort_unless(auth()->user()->can('prospects.manage'), 403);
        $this->validate(['file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv']], [], ['file' => 'planilha']);

        // Cópia local: o upload temporário do Livewire pode estar em outro disco.
        $path = tempnam(sys_get_temp_dir(), 'interessados');
        file_put_contents($path, (string) $this->file->get());

        try {
            $result = $action->handle($path, $this->file->getClientOriginalExtension());
        } finally {
            @unlink($path);
        }

        $this->reset('file');
        Flux::modal('import-prospects')->close();
        Flux::toast(variant: 'success', text: "Importação concluída: {$result['created']} novo(s), {$result['updated']} atualizado(s)"
            .($result['invalid'] ? ", {$result['invalid']} sem telefone válido" : '').'.');
        unset($this->prospects, $this->stats);
    }

    public function assign(): void
    {
        $user = auth()->user();
        abort_unless($user->can('prospects.manage'), 403);

        $allowed = Visibility::assignableExecutives($user)->pluck('id')->all();
        $executiveId = $this->assignTo === 'ninguem' ? null : (int) $this->assignTo;
        if ($executiveId !== null && ! in_array($executiveId, $allowed, true)) {
            $this->addError('assignTo', 'Escolha um executivo.');

            return;
        }

        $count = Prospect::query()->whereIn('id', $this->selected)->where('status', ProspectStatus::New->value)
            ->update(['executive_id' => $executiveId, 'assigned_at' => $executiveId ? now() : null]);

        $this->selected = [];
        $this->assignTo = '';
        Flux::toast(variant: 'success', text: $executiveId ? "{$count} interessado(s) atribuído(s)." : "{$count} interessado(s) sem executivo.");
        unset($this->prospects, $this->stats);
    }

    public function confirmDiscard(int $id): void
    {
        $this->authorize('discard', Prospect::query()->findOrFail($id));
        $this->discardId = $id;
        $this->discardReason = '';
        $this->resetValidation();
        Flux::modal('discard-prospect')->show();
    }

    public function discard(): void
    {
        $prospect = Prospect::query()->findOrFail($this->discardId);
        $this->authorize('discard', $prospect);
        $this->validate(['discardReason' => ['required', 'in:'.implode(',', array_keys(ProspectStatus::discardReasons()))]], [], ['discardReason' => 'motivo']);

        $prospect->update([
            'status' => ProspectStatus::Discarded, 'discard_reason' => $this->discardReason,
            'discarded_at' => now(), 'handled_by' => auth()->id(),
        ]);

        Flux::modal('discard-prospect')->close();
        Flux::toast(text: 'Interessado descartado.');
        unset($this->prospects, $this->stats);
    }

    public function reopen(int $id): void
    {
        abort_unless(auth()->user()->can('prospects.manage'), 403);
        Prospect::query()->whereKey($id)->where('status', ProspectStatus::Discarded->value)
            ->update(['status' => ProspectStatus::New->value, 'discard_reason' => null, 'discarded_at' => null]);
        unset($this->prospects, $this->stats);
    }

    #[On('lead-saved')]
    public function refreshList(): void
    {
        unset($this->prospects, $this->stats);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.prospects.index', [
            'statuses' => ProspectStatus::cases(),
            'reasons' => ProspectStatus::discardReasons(),
            'executives' => $user->can('prospects.view_all') ? Visibility::assignableExecutives($user) : collect(),
            'canManage' => $user->can('prospects.manage'),
        ]);
    }
}
