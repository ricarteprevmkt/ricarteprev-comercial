<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\SaveVisit;
use App\Enums\VisitPriority;
use App\Enums\VisitType;
use App\Livewire\Concerns\LoadsLocationOptions;
use App\Models\City;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Visit;
use App\Support\Visibility;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de agendamento de visita. O contato é buscado pelo nome; se ainda
 * não houver cadastro, basta informar o nome de quem será visitado.
 */
class VisitForm extends Component
{
    use LoadsLocationOptions;

    public ?int $visitId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public string $leadSearch = '';

    public bool $saveLocationOnLead = true;

    public function mount(): void
    {
        $this->resetFields();
    }

    #[On('visit-form:create')]
    public function create(?string $date = null, ?int $leadId = null, ?int $opportunityId = null): void
    {
        abort_unless(auth()->user()->can('agenda.manage'), 403);
        $this->resetFields($date);

        if ($leadId) {
            $this->selectLead($leadId);
        }
        if ($opportunityId) {
            $this->form['opportunity_id'] = $opportunityId;
        }

        Flux::modal('visit-form')->show();
    }

    #[On('visit-form:edit')]
    public function edit(int $id): void
    {
        $visit = Visit::query()->with('lead')->findOrFail($id);
        $this->authorize('update', $visit);
        $this->resetFields();
        $this->visitId = $visit->id;
        $this->form = [
            'executive_id' => $visit->executive_id,
            'lead_id' => $visit->lead_id,
            'opportunity_id' => $visit->opportunity_id,
            'contact_name' => $visit->contact_name ?? '',
            'type' => $visit->type->value,
            'priority' => $visit->priority->value,
            'date' => $visit->scheduled_at->toDateString(),
            'time' => $visit->scheduled_at->format('H:i'),
            'address' => $visit->address ?? '',
            'city_id' => $visit->city_id,
            'neighborhood' => $visit->neighborhood ?? '',
            'latitude' => $visit->location_source === 'manual' ? $visit->latitude : null,
            'longitude' => $visit->location_source === 'manual' ? $visit->longitude : null,
            'notes' => $visit->notes ?? '',
        ];
        $this->leadSearch = $visit->lead->name ?? '';
        Flux::modal('visit-form')->show();
    }

    public function selectLead(int $id): void
    {
        $lead = Lead::query()->visibleTo(auth()->user(), 'agenda.view_all')->findOrFail($id);
        $this->form['lead_id'] = $lead->id;
        $this->form['contact_name'] = '';
        $this->form['opportunity_id'] = $lead->opportunities()->open()->latest('last_activity_at')->value('id');
        $this->form['address'] = $this->form['address'] ?: ($lead->address ?? '');
        $this->form['city_id'] = $this->form['city_id'] ?: $lead->city_id;
        $this->form['neighborhood'] = $this->form['neighborhood'] ?: ($lead->neighborhood ?? '');
        if (! $this->form['executive_id'] && $lead->executive_id) {
            $this->form['executive_id'] = $lead->executive_id;
        }
        if ($lead->latitude !== null && $this->form['latitude'] === null) {
            $this->form['latitude'] = $lead->latitude;
            $this->form['longitude'] = $lead->longitude;
        }
        $this->leadSearch = $lead->name;
        $this->centerOnCity();
    }

    public function clearLead(): void
    {
        $this->form['lead_id'] = null;
        $this->form['opportunity_id'] = null;
        $this->leadSearch = '';
    }

    public function updatedFormCityId(): void
    {
        $this->centerOnCity();
    }

    private function centerOnCity(): void
    {
        $city = $this->form['city_id'] ? City::query()->find((int) $this->form['city_id']) : null;
        if ($city && $city->latitude !== null) {
            $this->dispatch('pin-picker-center', center: ['lat' => (float) $city->latitude, 'lng' => (float) $city->longitude]);
        }
    }

    public function save(SaveVisit $action): void
    {
        $user = auth()->user();
        $visit = $this->visitId ? Visit::query()->findOrFail($this->visitId) : null;
        $visit ? $this->authorize('update', $visit) : abort_unless($user->can('agenda.manage'), 403);

        $allowed = $this->executives()->pluck('id')->all();

        $this->validate([
            'form.executive_id' => ['required', 'integer', 'in:'.implode(',', $allowed ?: [0])],
            'form.lead_id' => ['nullable', 'integer'],
            'form.opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'form.contact_name' => ['nullable', 'string', 'max:255'],
            'form.type' => ['required', new Enum(VisitType::class)],
            'form.priority' => ['required', new Enum(VisitPriority::class)],
            'form.date' => ['required', 'date'],
            'form.time' => ['required', 'date_format:H:i'],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.city_id' => ['required', 'integer', 'exists:cities,id'],
            'form.neighborhood' => ['nullable', 'string', 'max:120'],
            'form.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'form.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'form.executive_id.in' => 'Escolha um executivo válido.',
        ], [
            'form.executive_id' => 'executivo', 'form.type' => 'tipo', 'form.date' => 'data', 'form.time' => 'horário',
            'form.city_id' => 'cidade', 'form.address' => 'endereço',
        ]);

        // O contato precisa ser visível para quem agenda.
        if ($this->form['lead_id'] && ! Lead::query()->visibleTo($user, 'agenda.view_all')->whereKey($this->form['lead_id'])->exists()) {
            $this->addError('form.lead_id', 'Contato não encontrado.');

            return;
        }

        try {
            $saved = $action->handle($visit, [
                'executive_id' => (int) $this->form['executive_id'],
                'lead_id' => $this->form['lead_id'] ? (int) $this->form['lead_id'] : null,
                'opportunity_id' => $this->form['opportunity_id'] ? (int) $this->form['opportunity_id'] : null,
                'contact_name' => $this->form['contact_name'] ?: null,
                'type' => VisitType::from($this->form['type']),
                'priority' => $this->form['priority'],
                'scheduled_at' => $this->form['date'].' '.$this->form['time'].':00',
                'address' => $this->form['address'] ?: null,
                'city_id' => (int) $this->form['city_id'],
                'neighborhood' => $this->form['neighborhood'] ?: null,
                'latitude' => $this->form['latitude'],
                'longitude' => $this->form['longitude'],
                'notes' => $this->form['notes'] ?: null,
            ], $user, $this->saveLocationOnLead);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal('visit-form')->close();
        Flux::toast(variant: 'success', text: $visit ? 'Visita atualizada.' : 'Visita agendada para '.$saved->scheduled_at->format('d/m \à\s H:i').'.');
        $this->dispatch('visit-saved', date: $saved->scheduled_at->toDateString());
        $this->resetFields();
    }

    private function resetFields(?string $date = null): void
    {
        $this->resetValidation();
        $this->visitId = null;
        $this->leadSearch = '';
        $this->saveLocationOnLead = true;
        $this->form = [
            'executive_id' => auth()->user()?->executive()->value('id'),
            'lead_id' => null, 'opportunity_id' => null, 'contact_name' => '',
            'type' => VisitType::FieldVisit->value, 'priority' => VisitPriority::Normal->value,
            'date' => $date ?: CarbonImmutable::today()->toDateString(), 'time' => '09:00',
            'address' => '', 'city_id' => null, 'neighborhood' => '', 'latitude' => null, 'longitude' => null, 'notes' => '',
        ];
    }

    /** @return Collection<int, Executive> */
    private function executives(): Collection
    {
        $user = auth()->user();

        return $user->can('agenda.view_all')
            ? Visibility::assignableExecutives($user, $this->form['executive_id'] ? (int) $this->form['executive_id'] : null)
            : Visibility::assignableExecutives($user)->where('user_id', $user->id)->values();
    }

    public function render(): View
    {
        $user = auth()->user();
        $leadResults = collect();

        if ($this->form['lead_id'] === null && mb_strlen($this->leadSearch) >= 2) {
            $leadResults = Lead::query()->visibleTo($user, 'agenda.view_all')
                ->where('name', 'like', "%{$this->leadSearch}%")
                ->with('city')->orderBy('name')->limit(8)->get();
        }

        $opportunities = $this->form['lead_id']
            ? Opportunity::query()->where('lead_id', $this->form['lead_id'])->open()->latest('created_at')->get()
            : collect();

        return view('livewire.agenda.visit-form', [
            'types' => VisitType::cases(),
            'priorities' => VisitPriority::cases(),
            'executives' => $this->executives(),
            'cities' => $this->cityOptions($this->form['city_id'] ? (int) $this->form['city_id'] : null),
            'leadResults' => $leadResults,
            'opportunities' => $opportunities,
        ]);
    }
}
