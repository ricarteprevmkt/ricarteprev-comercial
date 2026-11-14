<?php

use App\Actions\Crm\MoveOpportunity;
use App\Actions\Crm\SaveLead;
use App\Actions\Referrals\RegisterReferral;
use App\Actions\Referrals\SyncReferralStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\ReferralStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Livewire\Referrals\Portal;
use App\Livewire\Referrals\ReferralForm;
use App\Livewire\Referrals\ReferralList;
use App\Models\Catalogs\LossReason;
use App\Models\City;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Referral;
use App\Models\RegionalIndicator;
use App\Models\Visit;
use App\Notifications\ReferralAssigned;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->city = City::forceCreate(['ibge_code' => 2307304, 'name' => 'Juazeiro do Norte', 'uf' => 'CE', 'served' => true]);
    $this->manager = userWithRole(Role::Manager);
    $this->exec = Executive::factory()->create();
    $this->exec->user->assignRole('executivo');
    $this->indicator = RegionalIndicator::factory()->create(['default_executive_id' => $this->exec->id, 'city_id' => $this->city->id]);
    $this->other = RegionalIndicator::factory()->create();
});

function refer(RegionalIndicator $indicator, array $overrides = []): Referral
{
    return app(RegisterReferral::class)->handle($indicator, array_merge([
        'name' => 'João da Silva', 'phone' => '(88) 99700-1234', 'city_id' => test()->city->id,
        'referred_at' => now()->toDateString(), 'consent' => true,
    ], $overrides), test()->manager);
}

it('cria o contato com origem Indicações, atribui ao indicador e avisa o executivo', function () {
    Notification::fake();

    $referral = refer($this->indicator);
    $lead = $referral->lead;

    expect($referral->is_duplicate)->toBeFalse()
        ->and($referral->status)->toBe(ReferralStatus::Referred)
        ->and($referral->executive_id)->toBe($this->exec->id)
        ->and($lead->regional_indicator_id)->toBe($this->indicator->id)
        ->and($lead->source->slug)->toBe('indicacoes')
        ->and($lead->executive_id)->toBe($this->exec->id);

    Notification::assertSentTo($this->exec->user, ReferralAssigned::class);
});

it('registra como duplicada a indicação de um contato que já existia', function () {
    $lead = Lead::factory()->create(['phone' => '88997001234']);

    $referral = refer($this->indicator);

    expect($referral->is_duplicate)->toBeTrue()
        ->and($referral->lead_id)->toBe($lead->id)
        ->and($lead->fresh()->regional_indicator_id)->toBeNull()
        ->and(Lead::count())->toBe(1);
});

it('mantém a atribuição do primeiro indicador dentro da janela de 90 dias', function () {
    $first = refer($this->indicator, ['referred_at' => now()->subDays(30)->toDateString()]);
    $second = refer($this->other);

    expect($second->is_duplicate)->toBeTrue()
        ->and($second->duplicate_of_referral_id)->toBe($first->id)
        ->and($first->lead->fresh()->regional_indicator_id)->toBe($this->indicator->id);
});

it('passa a atribuição ao novo indicador depois da janela, se o contato não for cliente', function () {
    $first = refer($this->indicator, ['referred_at' => now()->subDays(120)->toDateString()]);
    $opportunity = app(SaveLead::class)->openOpportunity($first->lead, ['stage' => OpportunityStage::Contact], null);

    $second = refer($this->other);

    expect($second->is_duplicate)->toBeFalse()
        ->and($first->lead->fresh()->regional_indicator_id)->toBe($this->other->id)
        ->and($opportunity->fresh()->regional_indicator_id)->toBe($this->other->id);
});

it('não muda a atribuição de quem já é cliente, mesmo depois da janela', function () {
    $first = refer($this->indicator, ['referred_at' => now()->subDays(200)->toDateString()]);
    $first->lead->forceFill(['status' => LeadStatus::Client])->save();

    expect(refer($this->other)->is_duplicate)->toBeTrue();
});

it('acompanha o funil: atendimento agendado vira oportunidade gerada, e assim por diante', function () {
    $referral = refer($this->indicator);
    $opportunity = app(SaveLead::class)->openOpportunity($referral->lead, ['stage' => OpportunityStage::Contact], null);
    expect($referral->fresh()->status)->toBe(ReferralStatus::Contacted);

    app(MoveOpportunity::class)->handle($opportunity, OpportunityStage::Scheduled, null);
    expect($referral->fresh()->status)->toBe(ReferralStatus::Opportunity);

    app(MoveOpportunity::class)->handle($opportunity->fresh(), OpportunityStage::FollowUp, null);
    expect($referral->fresh()->status)->toBe(ReferralStatus::Opportunity)
        ->and($referral->fresh()->furthest_rank)->toBe(4);

    $reason = LossReason::firstOrFail();
    app(MoveOpportunity::class)->handle($opportunity->fresh(), OpportunityStage::Lost, null, lossReasonId: $reason->id);
    expect($referral->fresh()->status)->toBe(ReferralStatus::Lost)
        ->and($referral->histories()->count())->toBeGreaterThanOrEqual(4);
});

it('visita concluída marca contato realizado quando ainda não há oportunidade', function () {
    $referral = refer($this->indicator);

    Visit::factory()->create(['lead_id' => $referral->lead_id, 'executive_id' => $this->exec->id, 'scheduled_at' => now()->addHour()])
        ->update(['status' => VisitStatus::Done]);

    expect($referral->fresh()->status)->toBe(ReferralStatus::Contacted);
});

it('permite marcar manualmente, exigindo motivo para perdida, e bloqueia contrato assinado manual', function () {
    $referral = refer($this->indicator);

    Livewire::actingAs($this->manager)->test(ReferralList::class)
        ->call('openChange', $referral->id, 'lost')->call('saveChange')->assertHasErrors('change.note')
        ->set('change.note', 'Não quis atendimento')->call('saveChange')->assertHasNoErrors();

    expect($referral->fresh()->status)->toBe(ReferralStatus::Lost);
    expect(fn () => app(SyncReferralStatus::class)->manual($referral->fresh(), ReferralStatus::Signed, $this->manager, null))
        ->toThrow(ValidationException::class);
});

it('exige confirmação de consentimento no formulário', function () {
    Livewire::actingAs($this->manager)->test(ReferralForm::class)
        ->set('form.regional_indicator_id', $this->indicator->id)
        ->set('form.name', 'Maria')
        ->set('form.phone', '(88) 99111-0000')
        ->set('form.city_id', $this->city->id)
        ->call('save')
        ->assertHasErrors('form.consent')
        ->set('form.consent', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Referral::count())->toBe(1)->and(Referral::first()->consent_confirmed)->toBeTrue();
});

it('o indicador registra só em nome próprio e vê só as próprias indicações', function () {
    $user = userWithRole(Role::Indicator);
    $this->indicator->update(['user_id' => $user->id]);
    refer($this->other, ['name' => 'Indicação de outra pessoa', 'phone' => '(88) 99000-0001']);

    Livewire::actingAs($user)->test(ReferralForm::class)
        ->set('form.name', 'Vizinha do bairro')
        ->set('form.phone', '(88) 99222-0000')
        ->set('form.city_id', $this->city->id)
        ->set('form.consent', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Referral::latest('id')->first()->regional_indicator_id)->toBe($this->indicator->id);

    Livewire::actingAs($user)->test(Portal::class)
        ->assertSee('Vizinha do bairro')
        ->assertDontSee('Indicação de outra pessoa')
        ->assertDontSee('Minhas comissões');

    $this->actingAs($user)->get(route('referrals.list'))->assertOk(); // lista tem recorte próprio
    $this->actingAs($user)->get(route('referrals.index'))->assertForbidden();
    $this->actingAs($user)->get(route('referrals.commissions'))->assertForbidden();
});

it('o executivo vê na lista só as indicações que atende', function () {
    refer($this->indicator, ['name' => 'Para o Darlan']);
    refer($this->other, ['name' => 'Sem responsável', 'phone' => '(88) 99000-0002']);

    Livewire::actingAs($this->exec->user)->test(ReferralList::class)
        ->assertSee('Para o Darlan')
        ->assertDontSee('Sem responsável');
});
