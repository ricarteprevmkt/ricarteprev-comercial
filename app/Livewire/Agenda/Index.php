<?php

namespace App\Livewire\Agenda;

use App\Actions\Agenda\VisitWorkflow;
use App\Enums\ExpenseKind;
use App\Enums\VisitStatus;
use App\Metrics\AgendaMetrics;
use App\Models\Catalogs\VisitReason;
use App\Models\City;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\Route;
use App\Models\Visit;
use App\Support\Visibility;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Agenda e rotas: agenda do dia (ou da semana), mapa com a rota estimada,
 * resumo do dia, executivos em campo e despesas do dia.
 */
#[Title('Agenda e rotas')]
class Index extends Component
{
    #[Url(as: 'dia')]
    public string $date = '';

    #[Url(as: 'ver', except: 'dia')]
    public string $mode = 'dia';

    #[Url(as: 'executivo', except: '')]
    public string $executiveFilter = '';

    public ?int $selectedId = null;

    /** @var array<string, mixed> */
    public array $result = [];

    /** @var array{reason_id: int|string|null, note: string} */
    public array $notDone = ['reason_id' => null, 'note' => ''];

    /** @var array{date: string, time: string, note: string} */
    public array $reschedule = ['date' => '', 'time' => '', 'note' => ''];

    public function mount(): void
    {
        $this->authorize('viewAny', Visit::class);

        try {
            $this->date = CarbonImmutable::parse($this->date ?: 'today')->toDateString();
        } catch (\Throwable) {
            $this->date = CarbonImmutable::today()->toDateString();
        }
    }

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date)->startOfDay();
    }

    public function shiftDay(int $days): void
    {
        $this->date = $this->day()->addDays($this->mode === 'semana' ? $days * 7 : $days)->toDateString();
        $this->refreshMap();
    }

    public function goToday(): void
    {
        $this->date = CarbonImmutable::today()->toDateString();
        $this->refreshMap();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['date', 'executiveFilter', 'mode'], true)) {
            $this->refreshMap();
        }
    }

    #[On('visit-saved')]
    public function visitSaved(?string $date = null): void
    {
        if ($date) {
            $this->date = $date;
        }
        $this->refreshMap();
    }

    #[On('expense-saved')]
    public function expenseSaved(): void
    {
        unset($this->expenses, $this->summary);
    }

    private function refreshMap(): void
    {
        unset($this->visits, $this->summary, $this->expenses, $this->team);
        $this->dispatch('agenda-map', data: $this->mapData());
    }

    /** @return list<int>|null */
    private function scope(): ?array
    {
        $user = auth()->user();

        if ($user->can('agenda.view_all')) {
            return $this->executiveFilter !== '' ? [(int) $this->executiveFilter] : null;
        }

        $id = $user->executive()->value('id');

        return $id ? [(int) $id] : [];
    }

    // ---------- Ações sobre a visita ----------

    private function visit(int $id): Visit
    {
        $visit = Visit::query()->findOrFail($id);
        $this->authorize('update', $visit);

        return $visit;
    }

    public function confirm(int $id, VisitWorkflow $flow): void
    {
        $this->run(fn () => $flow->confirm($this->visit($id)), 'Visita confirmada.');
    }

    /** Coordenadas vêm do navegador, com permissão do usuário; podem ser nulas. */
    public function start(int $id, ?float $lat, ?float $lng, VisitWorkflow $flow): void
    {
        $valid = $lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180;
        $this->run(fn () => $flow->start($this->visit($id), $valid ? $lat : null, $valid ? $lng : null),
            $valid ? 'Visita iniciada. Localização registrada.' : 'Visita iniciada sem localização.');
    }

    public function openResult(int $id): void
    {
        $visit = $this->visit($id);
        $this->resetValidation();
        $this->selectedId = $visit->id;
        $start = $visit->started_at ?? $visit->scheduled_at;
        $end = now()->isSameDay($start) && now()->gt($start) ? now() : $start->addMinutes(30);
        $this->result = [
            'started_at' => $start->format('H:i'),
            'finished_at' => $end->format('H:i'),
            'outcome' => '', 'next_action' => '', 'next_contact_at' => '', 'km_reported' => '',
            'open_opportunity' => false,
        ];
        Flux::modal('visit-result')->show();
    }

    public function saveResult(VisitWorkflow $flow): void
    {
        $visit = $this->visit((int) $this->selectedId);

        $this->validate([
            'result.started_at' => ['required', 'date_format:H:i'],
            'result.finished_at' => ['required', 'date_format:H:i'],
            'result.outcome' => ['required', 'string', 'max:2000'],
            'result.next_action' => ['nullable', 'string', 'max:255'],
            'result.next_contact_at' => ['nullable', 'date', 'after_or_equal:today'],
            'result.km_reported' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'result.open_opportunity' => ['boolean'],
        ], attributes: [
            'result.started_at' => 'início', 'result.finished_at' => 'término', 'result.outcome' => 'resultado',
            'result.next_contact_at' => 'data do próximo contato', 'result.km_reported' => 'quilometragem',
        ]);

        $day = $visit->scheduled_at->toDateString();

        try {
            $flow->complete($visit, [
                'started_at' => "{$day} {$this->result['started_at']}:00",
                'finished_at' => "{$day} {$this->result['finished_at']}:00",
                'outcome' => $this->result['outcome'],
                'next_action' => $this->result['next_action'] ?: null,
                'next_contact_at' => $this->result['next_contact_at'] ?: null,
                'km_reported' => $this->result['km_reported'] !== '' ? (string) $this->result['km_reported'] : null,
                'open_opportunity' => (bool) $this->result['open_opportunity'],
            ], auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal('visit-result')->close();
        Flux::toast(variant: 'success', text: 'Resultado registrado.');
        $this->refreshMap();
    }

    public function openNotDone(int $id): void
    {
        $this->visit($id);
        $this->resetValidation();
        $this->selectedId = $id;
        $this->notDone = ['reason_id' => null, 'note' => ''];
        Flux::modal('visit-not-done')->show();
    }

    public function saveNotDone(VisitWorkflow $flow): void
    {
        $visit = $this->visit((int) $this->selectedId);
        $this->validate([
            'notDone.reason_id' => ['required', 'integer', 'exists:visit_reasons,id'],
            'notDone.note' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['notDone.reason_id' => 'motivo']);

        $this->run(fn () => $flow->notDone($visit, (int) $this->notDone['reason_id'], $this->notDone['note'] ?: null), 'Visita marcada como não realizada.');
        Flux::modal('visit-not-done')->close();
    }

    public function openReschedule(int $id): void
    {
        $visit = $this->visit($id);
        $this->resetValidation();
        $this->selectedId = $id;
        $this->reschedule = ['date' => $visit->scheduled_at->addDay()->toDateString(), 'time' => $visit->scheduled_at->format('H:i'), 'note' => ''];
        Flux::modal('visit-reschedule')->show();
    }

    public function saveReschedule(VisitWorkflow $flow): void
    {
        $visit = $this->visit((int) $this->selectedId);
        $this->validate([
            'reschedule.date' => ['required', 'date', 'after_or_equal:today'],
            'reschedule.time' => ['required', 'date_format:H:i'],
            'reschedule.note' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['reschedule.date' => 'nova data', 'reschedule.time' => 'novo horário']);

        $this->run(fn () => $flow->reschedule($visit, "{$this->reschedule['date']} {$this->reschedule['time']}:00", $this->reschedule['note'] ?: null, auth()->user()), 'Visita reagendada.');
        Flux::modal('visit-reschedule')->close();
    }

    public function cancel(int $id, VisitWorkflow $flow): void
    {
        $this->run(fn () => $flow->cancel($this->visit($id), null), 'Visita cancelada.');
    }

    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: (string) collect($e->errors())->flatten()->first());

            return;
        }

        Flux::toast(variant: 'success', text: $success);
        $this->refreshMap();
    }

    // ---------- Dados da tela ----------

    /** @return Collection<int, Visit> */
    #[Computed]
    public function visits(): Collection
    {
        $from = $this->mode === 'semana' ? $this->day()->startOfWeek() : $this->day();
        $to = $this->mode === 'semana' ? $this->day()->endOfWeek() : $this->day()->endOfDay();
        $scope = $this->scope();

        return Visit::query()
            ->visibleTo(auth()->user(), 'agenda.view_all')
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))
            ->whereBetween('scheduled_at', [$from, $to])
            ->with(['lead', 'city', 'executive.user', 'notDoneReason', 'opportunity'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function summary(): array
    {
        $from = $this->mode === 'semana' ? $this->day()->startOfWeek() : $this->day();
        $to = $this->mode === 'semana' ? $this->day()->endOfWeek() : $this->day()->endOfDay();

        return (new AgendaMetrics($from, $to, $this->scope()))->summary();
    }

    /** @return Collection<int, Expense> */
    #[Computed]
    public function expenses(): Collection
    {
        $scope = $this->scope();

        return Expense::query()
            ->visibleTo(auth()->user(), 'finance.view')
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))
            ->where('kind', ExpenseKind::Actual->value)
            ->whereDate('spent_on', $this->date)
            ->with(['category', 'executive.user'])
            ->latest('id')
            ->get();
    }

    /**
     * Executivos com agenda no dia: progresso e último check-in (equipe).
     *
     * @return Collection<int, array{executive: Executive, done: int, planned: int, last_checkin: Visit|null}>
     */
    #[Computed]
    public function team(): Collection
    {
        if (! auth()->user()->can('agenda.view_all') || $this->mode === 'semana') {
            return collect();
        }

        return Visit::query()->onDate($this->day())->with(['executive.user', 'city'])->get()
            ->groupBy('executive_id')
            ->map(fn (Collection $visits) => [
                'executive' => $visits->first()->executive,
                'done' => $visits->where('status', VisitStatus::Done)->count(),
                'planned' => $visits->filter(fn (Visit $v) => $v->status->isPlanned())->count(),
                'last_checkin' => $visits->whereNotNull('checkin_at')->sortByDesc('checkin_at')->first(),
            ])
            ->sortBy(fn ($row) => $row['executive']->user->name)
            ->values();
    }

    /** @return array{stops: array<int, array<string, mixed>>, base: array<string, mixed>|null, checkins: array<int, array<string, mixed>>} */
    public function mapData(): array
    {
        $scope = $this->scope();
        $from = $this->mode === 'semana' ? $this->day()->startOfWeek() : $this->day();
        $to = $this->mode === 'semana' ? $this->day()->endOfWeek() : $this->day();

        $routes = Route::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with(['stops.visit.lead', 'stops.visit.city', 'executive.user'])
            ->get();

        $stops = [];
        foreach ($routes as $route) {
            foreach ($route->stops as $stop) {
                $v = $stop->visit;
                $stops[] = [
                    'lat' => (float) $v->latitude, 'lng' => (float) $v->longitude, 'seq' => $stop->sequence,
                    'color' => $v->status->mapColor(), 'approximate' => $v->location_source === 'city',
                    'executive' => $route->executive_id.'-'.$route->date->toDateString(),
                    'title' => $v->displayName(),
                    'subtitle' => $v->scheduled_at->format('d/m H:i').' · '.$v->status->label().' · '.$route->executive->user->name,
                ];
            }
        }

        $base = null;
        if ($scope !== null && count($scope) === 1 && $this->mode === 'dia') {
            $executive = Executive::query()->with('city')->find($scope[0]);
            if ($executive?->city && $executive->city->latitude !== null) {
                $base = ['lat' => (float) $executive->city->latitude, 'lng' => (float) $executive->city->longitude, 'label' => $executive->city->label()];
            }
        }

        $checkins = Visit::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))
            ->whereBetween('scheduled_at', [$from, $to->endOfDay()])
            ->whereNotNull('checkin_at')
            ->with('executive.user')
            ->get()
            ->groupBy('executive_id')
            ->map(fn (Collection $vs): ?Visit => $vs->sortByDesc('checkin_at')->first())
            ->filter()
            ->map(fn (Visit $v): array => [
                'lat' => (float) $v->checkin_latitude, 'lng' => (float) $v->checkin_longitude,
                'title' => $v->executive->user->name,
                'subtitle' => 'Último check-in às '.$v->checkin_at?->format('H:i'),
            ])->values()->all();

        return ['stops' => $stops, 'base' => $base, 'checkins' => $checkins];
    }

    public function render(): View
    {
        $user = auth()->user();
        $selected = $this->selectedId ? Visit::query()->with('lead')->find($this->selectedId) : null;

        return view('livewire.agenda.index', [
            'mapData' => $this->mapData(),
            'executives' => $user->can('agenda.view_all') ? Visibility::assignableExecutives($user) : collect(),
            'reasons' => VisitReason::query()->active()->ordered()->get(),
            'selected' => $selected,
            'routeKm' => Route::query()->whereDate('date', $this->date)->when($this->scope() !== null, fn ($q) => $q->whereIn('executive_id', $this->scope() ?: [0]))->sum('km_estimated'),
            'servedCities' => City::query()->where('served', true)->count(),
        ]);
    }
}
