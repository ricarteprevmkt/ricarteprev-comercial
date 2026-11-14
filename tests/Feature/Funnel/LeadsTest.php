<?php

use App\Enums\OpportunityStage;
use App\Enums\Role;
use App\Livewire\Funnel\LeadForm;
use App\Livewire\Funnel\Leads;
use App\Models\Catalogs\LeadSource;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->manager = userWithRole(Role::Manager);
    $this->executive = Executive::factory()->create();
    $this->executive->user->assignRole('executivo');
    $this->source = LeadSource::where('slug', 'instagram')->firstOrFail();
});

function leadForm($user, array $overrides = [])
{
    $component = Livewire::actingAs($user)->test(LeadForm::class);
    $data = array_merge([
        'name' => 'Maria Souza', 'phone' => '(88) 99812-3456', 'lead_source_id' => test()->source->id,
        'executive_id' => test()->executive->id,
    ], $overrides);

    foreach ($data as $field => $value) {
        $component->set("form.{$field}", $value);
    }

    return $component;
}

it('cadastra contato e abre oportunidade com histórico', function () {
    leadForm($this->manager)
        ->set('opportunity.stage', 'qualified')
        ->set('opportunity.estimated_value', '3500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $lead = Lead::firstOrFail();
    $opportunity = Opportunity::firstOrFail();

    expect($lead->phone)->toBe('88998123456')
        ->and($opportunity->stage)->toBe(OpportunityStage::Qualified)
        ->and($opportunity->furthest_stage_rank)->toBe(2)
        ->and($opportunity->executive_id)->toBe($this->executive->id)
        ->and($opportunity->lead_source_id)->toBe($this->source->id)
        ->and($opportunity->histories()->count())->toBe(1);
});

it('cadastra contato sem oportunidade quando o usuário desliga a opção', function () {
    leadForm($this->manager)->set('openOpportunity', false)->call('save')->assertHasNoErrors();

    expect(Lead::count())->toBe(1)->and(Opportunity::count())->toBe(0);
});

it('bloqueia CPF repetido e guarda o CPF criptografado', function () {
    leadForm($this->manager, ['document' => '529.982.247-25'])->call('save')->assertHasNoErrors();

    leadForm($this->manager, ['name' => 'Outra', 'phone' => '(88) 99000-1111', 'document' => '52998224725'])
        ->call('save')
        ->assertHasErrors('form.document');

    expect(Lead::count())->toBe(1)
        ->and(DB::table('leads')->value('document_encrypted'))->not->toContain('52998224725')
        ->and(Lead::first()->document())->toBe('52998224725');
});

it('valida CNPJ para pessoa jurídica', function () {
    leadForm($this->manager, ['type' => 'pj', 'name' => 'AgroCariri', 'document' => '11.222.333/0001-80'])->call('save')->assertHasErrors('form.document');
    leadForm($this->manager, ['type' => 'pj', 'name' => 'AgroCariri', 'document' => '11.222.333/0001-81'])->call('save')->assertHasNoErrors();
});

it('avisa telefone repetido e permite cadastrar se o usuário confirmar que é outra pessoa', function () {
    leadForm($this->manager)->call('save');

    leadForm($this->manager, ['name' => 'José Souza'])
        ->call('save')
        ->assertHasErrors('form.phone')
        ->assertSet('samePhoneWarning', true)
        ->set('allowSamePhone', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Lead::count())->toBe(2);
});

it('exige telefone ou WhatsApp, origem e responsável', function () {
    leadForm($this->manager, ['phone' => '', 'lead_source_id' => null, 'executive_id' => null])
        ->call('save')
        ->assertHasErrors(['form.phone', 'form.lead_source_id', 'form.executive_id']);
});

it('o executivo só pode se colocar como responsável', function () {
    $other = Executive::factory()->create();

    leadForm($this->executive->user, ['executive_id' => $other->id])
        ->call('save')
        ->assertHasErrors('form.executive_id');

    leadForm($this->executive->user)->call('save')->assertHasNoErrors();
    expect(Lead::first()->executive_id)->toBe($this->executive->id);
});

it('o executivo vê só os próprios contatos; o gestor vê todos', function () {
    Lead::factory()->create(['name' => 'Cliente do Darlan', 'executive_id' => $this->executive->id]);
    Lead::factory()->create(['name' => 'Cliente de Outro']);

    Livewire::actingAs($this->executive->user)->test(Leads::class)
        ->assertSee('Cliente do Darlan')
        ->assertDontSee('Cliente de Outro');

    Livewire::actingAs($this->manager)->test(Leads::class)
        ->assertSee('Cliente do Darlan')
        ->assertSee('Cliente de Outro');
});

it('o executivo não edita contato de outro executivo', function () {
    $lead = Lead::factory()->create();

    Livewire::actingAs($this->executive->user)->test(LeadForm::class)
        ->call('edit', $lead->id)
        ->assertForbidden();
});

it('abre nova oportunidade para um contato existente', function () {
    $lead = Lead::factory()->create(['executive_id' => $this->executive->id]);

    Livewire::actingAs($this->manager)->test(Leads::class)
        ->call('startOpportunity', $lead->id)
        ->set('opportunity.stage', 'contact')
        ->call('createOpportunity')
        ->assertRedirect();

    expect($lead->opportunities()->count())->toBe(1);
});

it('nega o funil ao indicador', function () {
    $indicator = userWithRole(Role::Indicator);

    $this->actingAs($indicator)->get(route('funnel.leads'))->assertForbidden();
    $this->actingAs($indicator)->get(route('funnel.index'))->assertForbidden();
});
