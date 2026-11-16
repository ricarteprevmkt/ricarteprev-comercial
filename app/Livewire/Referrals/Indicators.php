<?php

namespace App\Livewire\Referrals;

use App\Actions\Indicators\SaveIndicator;
use App\Enums\IndicatorStatus;
use App\Livewire\Concerns\LoadsLocationOptions;
use App\Models\Catalogs\ReferralChannel;
use App\Models\CommissionRule;
use App\Models\RegionalIndicator;
use App\Rules\BrazilianPhone;
use App\Support\Phone;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Cadastro de parceiros')]
class Indicators extends Component
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

    public bool $hasAccess = false;

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public function mount(): void
    {
        $this->authorize('indicators.manage');
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
        $this->resetForm();
        Flux::modal('indicator-form')->show();
    }

    public function edit(int $id): void
    {
        $indicator = RegionalIndicator::query()->with('user')->findOrFail($id);
        $this->resetValidation();
        $this->photo = null;
        $this->editingId = $indicator->id;
        $this->hasAccess = $indicator->hasActiveAccess();
        $this->form = [
            'name' => $indicator->name,
            'phone' => Phone::format($indicator->phone),
            'whatsapp' => Phone::format($indicator->whatsapp),
            'email' => $indicator->email ?? '',
            'city_id' => $indicator->city_id,
            'neighborhood' => $indicator->neighborhood ?? '',
            'region_id' => $indicator->region_id,
            'default_executive_id' => $indicator->default_executive_id,
            'referral_channel_id' => $indicator->referral_channel_id,
            'commission_rule_id' => $indicator->commission_rule_id,
            'registered_at' => $indicator->registered_at->toDateString(),
            'status' => $indicator->status->value,
            'notes' => $indicator->notes ?? '',
        ];
        Flux::modal('indicator-form')->show();
    }

    public function save(SaveIndicator $action): void
    {
        $this->authorize('indicators.manage');
        $indicator = $this->editingId ? RegionalIndicator::query()->with('user')->findOrFail($this->editingId) : null;

        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.phone' => ['nullable', new BrazilianPhone],
            'form.whatsapp' => ['required', new BrazilianPhone],
            'form.email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($indicator?->user_id)],
            'form.city_id' => ['required', 'integer', 'exists:cities,id'],
            'form.neighborhood' => ['nullable', 'string', 'max:120'],
            'form.region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'form.default_executive_id' => ['nullable', 'integer', 'exists:executives,id'],
            'form.referral_channel_id' => ['nullable', 'integer', 'exists:referral_channels,id'],
            'form.commission_rule_id' => ['nullable', 'integer', 'exists:commission_rules,id'],
            'form.registered_at' => ['required', 'date', 'before_or_equal:today'],
            'form.status' => ['required', new Enum(IndicatorStatus::class)],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'form.email.unique' => 'Este e-mail já é usado por outro acesso ao sistema.',
        ], [
            'form.name' => 'nome', 'form.phone' => 'telefone', 'form.whatsapp' => 'WhatsApp', 'form.email' => 'e-mail',
            'form.city_id' => 'cidade', 'form.neighborhood' => 'bairro', 'form.region_id' => 'região',
            'form.registered_at' => 'data de cadastro', 'photo' => 'foto',
        ]);

        // O WhatsApp identifica o parceiro: não pode haver dois cadastros com o mesmo número.
        $whatsapp = Phone::normalize($this->form['whatsapp']);
        if (RegionalIndicator::withTrashed()->where('whatsapp', $whatsapp)->whereKeyNot($indicator->id ?? 0)->exists()) {
            $this->addError('form.whatsapp', 'Já existe um parceiro cadastrado com este WhatsApp.');

            return;
        }

        try {
            $result = $action->handle($indicator, [
                'name' => $this->form['name'],
                'phone' => Phone::normalize($this->form['phone']),
                'whatsapp' => $whatsapp,
                'email' => $this->form['email'] ?: null,
                'city_id' => (int) $this->form['city_id'],
                'neighborhood' => $this->form['neighborhood'] ?: null,
                'region_id' => $this->form['region_id'] ? (int) $this->form['region_id'] : null,
                'default_executive_id' => $this->form['default_executive_id'] ? (int) $this->form['default_executive_id'] : null,
                'referral_channel_id' => $this->form['referral_channel_id'] ? (int) $this->form['referral_channel_id'] : null,
                'commission_rule_id' => $this->form['commission_rule_id'] ? (int) $this->form['commission_rule_id'] : null,
                'registered_at' => $this->form['registered_at'],
                'status' => $this->form['status'],
                'notes' => $this->form['notes'] ?: null,
            ], $this->hasAccess, $this->photo, auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal('indicator-form')->close();
        $message = $indicator ? 'Parceiro atualizado.' : 'Parceiro cadastrado.';
        if ($result['reset_link_sent']) {
            $message .= ' Enviamos o link de criação de senha.';
        }
        Flux::toast(variant: 'success', text: $message);
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->photo = null;
        $this->hasAccess = false;
        $this->form = [
            'name' => '', 'phone' => '', 'whatsapp' => '', 'email' => '', 'city_id' => null, 'neighborhood' => '',
            'region_id' => null, 'default_executive_id' => null, 'referral_channel_id' => null, 'commission_rule_id' => null,
            'registered_at' => now()->toDateString(), 'status' => IndicatorStatus::Active->value, 'notes' => '',
        ];
    }

    /** @return LengthAwarePaginator<int, RegionalIndicator> */
    #[Computed]
    public function indicators(): LengthAwarePaginator
    {
        return RegionalIndicator::query()
            ->with(['city', 'region', 'commissionRule', 'user'])
            ->when($this->search !== '', function ($q) {
                $digits = Phone::normalize($this->search);
                $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->when($digits && strlen($digits) >= 4, fn ($q) => $q->orWhere('whatsapp', 'like', "%{$digits}%")));
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->regionFilter !== '', fn ($q) => $q->where('region_id', $this->regionFilter))
            ->orderByRaw("status = 'inactive'")
            ->orderBy('name')
            ->paginate(15);
    }

    public function render(): View
    {
        $selectedCity = $this->form['city_id'] ? (int) $this->form['city_id'] : null;
        $selectedRegion = $this->form['region_id'] ? (int) $this->form['region_id'] : null;

        return view('livewire.referrals.indicators', [
            'statuses' => IndicatorStatus::cases(),
            'cities' => $this->cityOptions($selectedCity),
            'regions' => $this->regionOptions($selectedRegion),
            'filterRegions' => $this->regionOptions(),
            'channels' => ReferralChannel::query()->active()->ordered()->get(),
            'rules' => CommissionRule::query()->active()->orderBy('name')->get(),
            'executives' => Visibility::assignableExecutives(auth()->user(), $this->form['default_executive_id'] ? (int) $this->form['default_executive_id'] : null),
        ]);
    }
}
