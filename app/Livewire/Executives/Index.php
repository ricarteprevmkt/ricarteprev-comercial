<?php

namespace App\Livewire\Executives;

use App\Actions\Executives\SaveExecutive;
use App\Enums\ExecutiveStatus;
use App\Livewire\Concerns\LoadsLocationOptions;
use App\Models\Executive;
use App\Models\Goal;
use App\Rules\BrazilianPhone;
use App\Rules\ValidCpf;
use App\Support\Cpf;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Cadastro de executivos e metas mensais. O painel de desempenho individual
 * entra na Fase 7, usando estes mesmos registros.
 */
#[Title('Executivos')]
class Index extends Component
{
    use LoadsLocationOptions, WithFileUploads, WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'regiao', except: '')]
    public string $regionFilter = '';

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public ?int $goalsFor = null;

    /** @var array{month: string, visits_target: int|string|null, contracts_target: int|string|null} */
    public array $goalForm = ['month' => '', 'visits_target' => null, 'contracts_target' => null];

    public function mount(): void
    {
        $this->authorize('executives.view');
        $this->resetForm();
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'regionFilter'], true)) {
            $this->resetPage();
        }
    }

    public function updatedFormCityId(mixed $value): void
    {
        if (empty($this->form['region_id'])) {
            $this->form['region_id'] = $this->regionForCity($value ? (int) $value : null);
        }
    }

    public function create(): void
    {
        $this->authorize('executives.manage');
        $this->resetForm();
        Flux::modal('executive-form')->show();
    }

    public function edit(int $id): void
    {
        $this->authorize('executives.manage');
        $executive = Executive::query()->with('user')->findOrFail($id);
        $this->resetValidation();
        $this->photo = null;
        $this->editingId = $executive->id;
        $this->form = [
            'name' => $executive->user->name,
            'email' => $executive->user->email,
            'phone' => Phone::format($executive->user->phone),
            'password' => '',
            'cpf' => Cpf::format($executive->cpf()),
            'city_id' => $executive->city_id,
            'region_id' => $executive->region_id,
            'hired_at' => $executive->hired_at?->toDateString(),
            'status' => $executive->status->value,
            'monthly_visits_target' => $executive->monthly_visits_target,
            'monthly_contracts_target' => $executive->monthly_contracts_target,
            'cost_allowance' => $executive->cost_allowance,
            'compensation_notes' => $executive->compensation_notes,
        ];
        Flux::modal('executive-form')->show();
    }

    public function save(SaveExecutive $action): void
    {
        $this->authorize('executives.manage');
        $executive = $this->editingId ? Executive::query()->with('user')->findOrFail($this->editingId) : null;

        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($executive?->user_id)],
            'form.phone' => ['nullable', new BrazilianPhone],
            'form.password' => ['nullable', Password::defaults()],
            'form.cpf' => ['nullable', new ValidCpf],
            'form.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'form.region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'form.hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'form.status' => ['required', new Enum(ExecutiveStatus::class)],
            'form.monthly_visits_target' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'form.monthly_contracts_target' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'form.cost_allowance' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999'],
            'form.compensation_notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], [
            'form.name' => 'nome', 'form.email' => 'e-mail', 'form.phone' => 'telefone', 'form.password' => 'senha',
            'form.cpf' => 'CPF', 'form.city_id' => 'cidade', 'form.region_id' => 'região', 'form.hired_at' => 'data de admissão',
            'form.monthly_visits_target' => 'meta de visitas', 'form.monthly_contracts_target' => 'meta de contratos',
            'form.cost_allowance' => 'ajuda de custo', 'photo' => 'foto',
        ]);

        // A duplicidade de CPF é checada pelo hash, no servidor, sem expor o hash ao navegador.
        $cpfHash = Cpf::hash($this->form['cpf'] ?: null);
        if ($cpfHash && Executive::withTrashed()->where('cpf_hash', $cpfHash)->whereKeyNot($executive->id ?? 0)->exists()) {
            $this->addError('form.cpf', 'Já existe um executivo com este CPF.');

            return;
        }

        $result = $action->handle($executive, [
            'name' => $this->form['name'],
            'email' => $this->form['email'],
            'phone' => Phone::normalize($this->form['phone']),
            'password' => $this->form['password'] ?: null,
            'cpf' => $this->form['cpf'] ?: null,
            'city_id' => $this->form['city_id'] ? (int) $this->form['city_id'] : null,
            'region_id' => $this->form['region_id'] ? (int) $this->form['region_id'] : null,
            'hired_at' => $this->form['hired_at'] ?: null,
            'status' => $this->form['status'],
            'monthly_visits_target' => $this->nullableInt($this->form['monthly_visits_target']),
            'monthly_contracts_target' => $this->nullableInt($this->form['monthly_contracts_target']),
            'cost_allowance' => $this->form['cost_allowance'] !== '' ? $this->form['cost_allowance'] : null,
            'compensation_notes' => $this->form['compensation_notes'] ?: null,
        ], $this->photo, auth()->user());

        Flux::modal('executive-form')->close();
        $message = $executive ? 'Executivo atualizado.' : 'Executivo cadastrado.';
        if ($result['reset_link_sent']) {
            $message .= ' Enviamos o link de criação de senha.';
        }
        Flux::toast(variant: 'success', text: $message);
        $this->resetForm();
    }

    public function openGoals(int $id): void
    {
        $this->authorize('goals.manage');
        $this->goalsFor = Executive::query()->findOrFail($id)->id;
        $this->resetValidation();
        $this->goalForm = ['month' => now()->format('Y-m'), 'visits_target' => null, 'contracts_target' => null];
        Flux::modal('goals')->show();
    }

    public function saveGoal(): void
    {
        $this->authorize('goals.manage');
        $executive = Executive::query()->findOrFail($this->goalsFor);

        $this->validate([
            'goalForm.month' => ['required', 'date_format:Y-m'],
            'goalForm.visits_target' => ['required', 'integer', 'min:0', 'max:9999'],
            'goalForm.contracts_target' => ['required', 'integer', 'min:0', 'max:9999'],
        ], attributes: [
            'goalForm.month' => 'mês', 'goalForm.visits_target' => 'meta de visitas', 'goalForm.contracts_target' => 'meta de contratos',
        ]);

        Goal::query()->updateOrCreate(
            ['executive_id' => $executive->id, 'month' => CarbonImmutable::createFromFormat('Y-m', $this->goalForm['month'])->startOfMonth()->toDateString()],
            [
                'visits_target' => (int) $this->goalForm['visits_target'],
                'contracts_target' => (int) $this->goalForm['contracts_target'],
                'created_by' => auth()->id(),
            ],
        );

        $this->goalForm['visits_target'] = null;
        $this->goalForm['contracts_target'] = null;
        Flux::toast(variant: 'success', text: 'Meta salva.');
    }

    public function deleteGoal(int $goalId): void
    {
        $this->authorize('goals.manage');
        Goal::query()->where('executive_id', $this->goalsFor)->whereKey($goalId)->firstOrFail()->delete();
        Flux::toast(text: 'Meta removida. Para esse mês vale a meta padrão.');
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->photo = null;
        $this->form = [
            'name' => '', 'email' => '', 'phone' => '', 'password' => '', 'cpf' => '',
            'city_id' => null, 'region_id' => null, 'hired_at' => null, 'status' => ExecutiveStatus::Active->value,
            'monthly_visits_target' => null, 'monthly_contracts_target' => null,
            'cost_allowance' => null, 'compensation_notes' => '',
        ];
    }

    /** @return LengthAwarePaginator<int, Executive> */
    #[Computed]
    public function executives(): LengthAwarePaginator
    {
        return Executive::query()
            ->with(['user', 'city', 'region'])
            ->join('users', 'users.id', '=', 'executives.user_id')
            ->select('executives.*')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('users.name', 'like', "%{$this->search}%")
                ->orWhere('users.email', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('executives.status', $this->statusFilter))
            ->when($this->regionFilter !== '', fn ($q) => $q->where('executives.region_id', $this->regionFilter))
            ->orderByRaw("executives.status = 'terminated'")
            ->orderBy('users.name')
            ->paginate(15);
    }

    /** @return Collection<int, Goal> */
    #[Computed]
    public function goals(): Collection
    {
        return $this->goalsFor
            ? Goal::query()->where('executive_id', $this->goalsFor)->orderByDesc('month')->limit(24)->get()
            : collect();
    }

    #[Computed]
    public function goalsExecutive(): ?Executive
    {
        return $this->goalsFor ? Executive::query()->with('user')->find($this->goalsFor) : null;
    }

    public function render(): View
    {
        $selectedCity = $this->form['city_id'] ? (int) $this->form['city_id'] : null;
        $selectedRegion = $this->form['region_id'] ? (int) $this->form['region_id'] : null;

        return view('livewire.executives.index', [
            'statuses' => ExecutiveStatus::cases(),
            'cities' => $this->cityOptions($selectedCity),
            'regions' => $this->regionOptions($selectedRegion),
            'filterRegions' => $this->regionOptions(),
        ]);
    }
}
