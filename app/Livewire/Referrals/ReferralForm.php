<?php

namespace App\Livewire\Referrals;

use App\Actions\Referrals\RegisterReferral;
use App\Livewire\Concerns\LoadsLocationOptions;
use App\Models\Catalogs\ReferralChannel;
use App\Models\RegionalIndicator;
use App\Rules\BrazilianPhone;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Registro de indicação. O parceiro com acesso ao sistema registra as próprias
 * (parceiro fixo, executivo definido pelo cadastro dele); a equipe escolhe o
 * parceiro e o executivo.
 */
class ReferralForm extends Component
{
    use LoadsLocationOptions;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->resetFields();
    }

    private function ownIndicator(): ?RegionalIndicator
    {
        return auth()->user()->regionalIndicator()->first();
    }

    private function isTeam(): bool
    {
        return auth()->user()->can('referrals.view_all') || auth()->user()->can('indicators.manage') || auth()->user()->executive()->exists();
    }

    #[On('referral-form:create')]
    public function create(): void
    {
        abort_unless(auth()->user()->can('referrals.create'), 403);
        $this->resetFields();
        Flux::modal('referral-form')->show();
    }

    public function save(RegisterReferral $action): void
    {
        $user = auth()->user();
        abort_unless($user->can('referrals.create'), 403);
        $team = $this->isTeam();
        $own = $this->ownIndicator();

        if (! $team && $own === null) {
            abort(403);
        }

        $executives = Visibility::assignableExecutives($user)->pluck('id')->all();

        $this->validate([
            'form.regional_indicator_id' => $team ? ['required', 'integer', 'exists:regional_indicators,id'] : ['nullable'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.phone' => ['required', new BrazilianPhone],
            'form.city_id' => ['required', 'integer', 'exists:cities,id'],
            'form.neighborhood' => ['nullable', 'string', 'max:120'],
            'form.referred_at' => ['required', 'date', 'before_or_equal:today'],
            'form.referral_channel_id' => ['nullable', 'integer', 'exists:referral_channels,id'],
            'form.executive_id' => $team ? ['nullable', 'integer', 'in:'.implode(',', $executives ?: [0])] : ['nullable'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
            'form.consent' => ['accepted'],
        ], [
            'form.consent.accepted' => 'Confirme que a pessoa indicada autorizou o contato.',
        ], [
            'form.regional_indicator_id' => 'parceiro', 'form.name' => 'nome', 'form.phone' => 'telefone',
            'form.city_id' => 'cidade', 'form.referred_at' => 'data da indicação', 'form.executive_id' => 'executivo',
        ]);

        $indicator = $team
            ? RegionalIndicator::query()->active()->findOrFail((int) $this->form['regional_indicator_id'])
            : $own;

        $referral = $action->handle($indicator, [
            'name' => $this->form['name'],
            'phone' => $this->form['phone'],
            'city_id' => (int) $this->form['city_id'],
            'neighborhood' => $this->form['neighborhood'] ?: null,
            'referred_at' => $team ? $this->form['referred_at'] : now()->toDateString(),
            'referral_channel_id' => $team && $this->form['referral_channel_id'] ? (int) $this->form['referral_channel_id'] : null,
            'executive_id' => $team && $this->form['executive_id'] ? (int) $this->form['executive_id'] : null,
            'notes' => $this->form['notes'] ?: null,
            'consent' => true,
        ], $user);

        Flux::modal('referral-form')->close();
        Flux::toast(
            variant: $referral->is_duplicate ? 'warning' : 'success',
            text: $referral->is_duplicate
                ? 'Este contato já estava cadastrado. A indicação foi registrada como duplicada, para análise.'
                : 'Indicação registrada.',
        );
        $this->dispatch('referral-saved');
        $this->resetFields();
    }

    private function resetFields(): void
    {
        $this->resetValidation();
        $this->form = [
            'regional_indicator_id' => null, 'name' => '', 'phone' => '', 'city_id' => null, 'neighborhood' => '',
            'referred_at' => now()->toDateString(), 'referral_channel_id' => null, 'executive_id' => null,
            'notes' => '', 'consent' => false,
        ];

        if (! $this->isTeam() && ($own = $this->ownIndicator())) {
            $this->form['city_id'] = $own->city_id;
        }
    }

    public function render(): View
    {
        $team = $this->isTeam();

        return view('livewire.referrals.referral-form', [
            'team' => $team,
            'indicators' => $team ? RegionalIndicator::query()->active()->with('city')->orderBy('name')->get() : collect(),
            'executives' => $team ? Visibility::assignableExecutives(auth()->user()) : collect(),
            'channels' => ReferralChannel::query()->active()->ordered()->get(),
            'cities' => $this->cityOptions($this->form['city_id'] ? (int) $this->form['city_id'] : null),
        ]);
    }
}
