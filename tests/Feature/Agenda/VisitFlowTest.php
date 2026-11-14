<?php

use App\Enums\OpportunityStage;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Livewire\Agenda\Index;
use App\Livewire\Agenda\VisitForm;
use App\Models\Catalogs\VisitReason;
use App\Models\City;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Route;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\SystemSettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 07:00:00'));
    $this->seed([CatalogSeeder::class, CitySeeder::class, SystemSettingsSeeder::class]);
    $this->juazeiro = City::where('ibge_code', 2307304)->firstOrFail();
    $this->crato = City::where('ibge_code', 2304202)->firstOrFail();
    City::whereKey([$this->juazeiro->id, $this->crato->id])->update(['served' => true]);

    $this->manager = userWithRole(Role::Manager);
    $this->exec = Executive::factory()->create(['city_id' => $this->juazeiro->id]);
    $this->exec->user->assignRole('executivo');
    $this->lead = Lead::factory()->create(['executive_id' => $this->exec->id, 'city_id' => $this->juazeiro->id, 'latitude' => '-7.2100000', 'longitude' => '-39.3100000']);
});

function schedule($user, array $form)
{
    $c = Livewire::actingAs($user)->test(VisitForm::class)->call('create', '2026-11-16');
    foreach ($form as $k => $v) {
        $k === 'lead' ? $c->call('selectLead', $v) : $c->set("form.{$k}", $v);
    }

    return $c->call('save');
}

it('agenda visita para um contato usando o ponto salvo no contato', function () {
    schedule($this->exec->user, ['lead' => $this->lead->id, 'time' => '08:30'])->assertHasNoErrors();

    $visit = Visit::firstOrFail();
    expect($visit->executive_id)->toBe($this->exec->id)
        ->and($visit->city_id)->toBe($this->juazeiro->id)
        ->and($visit->location_source)->toBe('lead')
        ->and($visit->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-16 08:30');
});

it('usa o centro da cidade como local aproximado quando não há ponto', function () {
    schedule($this->exec->user, ['contact_name' => 'Construtora Vale', 'city_id' => $this->crato->id])->assertHasNoErrors();

    $visit = Visit::firstOrFail();
    expect($visit->location_source)->toBe('city')
        ->and($visit->latitude)->toBe($this->crato->latitude);
});

it('exige contato cadastrado ou nome de quem será visitado', function () {
    schedule($this->exec->user, ['city_id' => $this->crato->id])->assertHasErrors('form.lead_id');
});

it('o executivo só agenda para si mesmo', function () {
    $other = Executive::factory()->create();

    schedule($this->exec->user, ['contact_name' => 'X', 'city_id' => $this->crato->id, 'executive_id' => $other->id])
        ->assertHasErrors('form.executive_id');
});

it('agendar atendimento avança a oportunidade para "Atendimento agendado"', function () {
    $opportunity = Opportunity::factory()->create(['lead_id' => $this->lead->id, 'executive_id' => $this->exec->id]);

    schedule($this->exec->user, ['lead' => $this->lead->id, 'type' => 'appointment'])->assertHasNoErrors();

    expect($opportunity->fresh()->stage)->toBe(OpportunityStage::Scheduled)
        ->and(Visit::first()->opportunity_id)->toBe($opportunity->id);
});

it('monta a rota do dia a partir da base do executivo, sem as canceladas', function () {
    schedule($this->exec->user, ['lead' => $this->lead->id, 'time' => '08:00']);
    schedule($this->exec->user, ['contact_name' => 'Crato', 'city_id' => $this->crato->id, 'time' => '10:00']);
    schedule($this->exec->user, ['contact_name' => 'Crato 2', 'city_id' => $this->crato->id, 'time' => '11:00']);

    $route = Route::with('stops')->firstOrFail();
    expect($route->stops_count)->toBe(3)
        ->and($route->has_approximate_points)->toBeTrue()
        ->and((float) $route->stops[2]->leg_km)->toBe(0.0);

    $before = (float) $route->km_estimated;
    Livewire::actingAs($this->exec->user)->test(Index::class)->call('cancel', Visit::where('contact_name', 'Crato 2')->value('id'));

    expect($route->fresh()->stops_count)->toBe(2)
        ->and((float) $route->fresh()->km_estimated)->toBe($before);
});

it('inicia com check-in, registra o resultado e atualiza a oportunidade', function () {
    $opportunity = Opportunity::factory()->stage(OpportunityStage::Qualified)->create(['lead_id' => $this->lead->id, 'executive_id' => $this->exec->id]);
    schedule($this->exec->user, ['lead' => $this->lead->id, 'time' => '09:00']);
    $visit = Visit::firstOrFail();

    $agenda = Livewire::actingAs($this->exec->user)->test(Index::class);
    $agenda->call('start', $visit->id, -7.2131, -39.315);

    $visit->refresh();
    expect($visit->status)->toBe(VisitStatus::InProgress)
        ->and($visit->checkin_at)->not->toBeNull()
        ->and((float) $visit->checkin_latitude)->toBe(-7.2131);

    $agenda->call('openResult', $visit->id)
        ->set('result.started_at', '09:05')
        ->set('result.finished_at', '09:50')
        ->set('result.outcome', 'Documentos entregues')
        ->set('result.next_action', 'Apresentar proposta')
        ->set('result.next_contact_at', '2026-11-20')
        ->set('result.km_reported', '6.5')
        ->call('saveResult')
        ->assertHasNoErrors();

    $visit->refresh();
    $opportunity->refresh();
    expect($visit->status)->toBe(VisitStatus::Done)
        ->and($visit->km_reported)->toBe('6.5')
        ->and($opportunity->next_action)->toBe('Apresentar proposta')
        ->and($opportunity->next_action_due->toDateString())->toBe('2026-11-20');
});

it('abre oportunidade a partir da visita e registra a origem', function () {
    schedule($this->exec->user, ['lead' => $this->lead->id]);
    $visit = Visit::firstOrFail();

    Livewire::actingAs($this->exec->user)->test(Index::class)
        ->call('openResult', $visit->id)
        ->set('result.outcome', 'Interessado')
        ->set('result.open_opportunity', true)
        ->call('saveResult')
        ->assertHasNoErrors();

    $opportunity = Opportunity::firstOrFail();
    expect($opportunity->visit_id)->toBe($visit->id)
        ->and($visit->fresh()->opportunity_id)->toBe($opportunity->id);
});

it('recusa término antes do início', function () {
    schedule($this->exec->user, ['lead' => $this->lead->id]);

    Livewire::actingAs($this->exec->user)->test(Index::class)
        ->call('openResult', Visit::first()->id)
        ->set('result.started_at', '10:00')
        ->set('result.finished_at', '09:00')
        ->set('result.outcome', 'x')
        ->call('saveResult')
        ->assertHasErrors('result.finished_at');
});

it('não realizada exige motivo', function () {
    schedule($this->exec->user, ['lead' => $this->lead->id]);
    $visit = Visit::firstOrFail();
    $reason = VisitReason::where('slug', 'ausencia-do-contato')->firstOrFail();

    $agenda = Livewire::actingAs($this->exec->user)->test(Index::class);
    $agenda->call('openNotDone', $visit->id)->call('saveNotDone')->assertHasErrors('notDone.reason_id');
    $agenda->call('openNotDone', $visit->id)->set('notDone.reason_id', $reason->id)->call('saveNotDone')->assertHasNoErrors();

    expect($visit->fresh()->status)->toBe(VisitStatus::NotDone)
        ->and($visit->fresh()->not_done_reason_id)->toBe($reason->id);
});

it('reagenda criando nova visita ligada à original', function () {
    schedule($this->exec->user, ['lead' => $this->lead->id, 'time' => '09:00']);
    $visit = Visit::firstOrFail();

    Livewire::actingAs($this->exec->user)->test(Index::class)
        ->call('openReschedule', $visit->id)
        ->set('reschedule.date', '2026-11-18')
        ->set('reschedule.time', '15:00')
        ->call('saveReschedule')
        ->assertHasNoErrors();

    $visit->refresh();
    $new = Visit::findOrFail($visit->rescheduled_to_id);

    expect($visit->status)->toBe(VisitStatus::Rescheduled)
        ->and($new->status)->toBe(VisitStatus::Scheduled)
        ->and($new->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-18 15:00')
        ->and(Route::whereDate('date', '2026-11-16')->exists())->toBeFalse()
        ->and(Route::whereDate('date', '2026-11-18')->exists())->toBeTrue();
});

it('o executivo não altera visita de outro executivo e não a vê na agenda', function () {
    $otherVisit = Visit::factory()->create(['contact_name' => 'Visita de outro executivo', 'scheduled_at' => '2026-11-16 10:00:00']);

    Livewire::actingAs($this->exec->user)->test(Index::class)
        ->assertDontSee('Visita de outro executivo')
        ->call('confirm', $otherVisit->id)
        ->assertForbidden();

    Livewire::actingAs($this->manager)->test(Index::class)->assertSee('Visita de outro executivo');
});

it('marca como sem resultado as visitas vencidas sem registro', function () {
    Visit::factory()->create(['executive_id' => $this->exec->id, 'scheduled_at' => '2026-11-14 09:00:00']);
    Visit::factory()->create(['executive_id' => $this->exec->id, 'scheduled_at' => '2026-11-16 06:00:00']);

    expect(Visit::withoutResult()->count())->toBe(1);
});
