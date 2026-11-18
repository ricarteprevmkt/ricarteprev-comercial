<?php

use App\Actions\Prospects\ImportProspects;
use App\Enums\OpportunityStage;
use App\Enums\ProspectStatus;
use App\Enums\Role;
use App\Livewire\Funnel\LeadForm;
use App\Livewire\Prospects\Index;
use App\Models\Catalogs\LeadSource;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Prospect;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
});

/** @param  list<list<string>>  $rows */
function prospectsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'leads').'.csv';
    $lines = array_map(fn (array $r) => implode(';', $r), array_merge([
        ['Nome', 'E-mail', 'Telefone', 'CPF', 'Origem', 'Pipeline', 'Etapa', 'Responsável', 'Demanda', 'Observações', 'Data de Criação'],
    ], $rows));
    file_put_contents($path, implode("\n", $lines));

    return $path;
}

it('importa a planilha do C2 sem duplicar e marca quem já é contato', function () {
    $exec = Executive::factory()->create();
    $lead = Lead::factory()->create(['executive_id' => $exec->id, 'name' => 'Contato Antigo', 'phone' => '87999990000']);

    $path = prospectsCsv([
        ['Maria Souza', '-', '5587988887777', '-', 'WhatsApp', 'Pipeline Principal', 'Novo Lead', '-', 'Aposentadoria rural', '-', '05/11/2026'],
        ['🌻', '-', '558788886666', '-', 'WhatsApp', 'Pipeline Principal', 'Novo Lead', '-', '-', '-', '06/11/2026'],
        // Mesmo celular do contato antigo, sem o 9 (como o WhatsApp às vezes grava).
        ['Antigo no WhatsApp', '-', '558799990000', '-', 'WhatsApp', 'Pipeline Principal', 'Novo Lead', '-', '-', '-', '07/11/2026'],
        ['Telefone ruim', '-', '123', '-', 'WhatsApp', 'Pipeline Principal', 'Novo Lead', '-', '-', '-', '07/11/2026'],
    ]);

    $result = app(ImportProspects::class)->handle($path, 'csv');

    expect($result)->toMatchArray(['created' => 3, 'updated' => 0, 'invalid' => 1, 'contacts' => 1]);
    $maria = Prospect::query()->where('name', 'Maria Souza')->sole();
    expect($maria->phone)->toBe('87988887777')
        ->and($maria->demand)->toBe('Aposentadoria rural')
        ->and($maria->entered_at->toDateString())->toBe('2026-11-05')
        ->and(Prospect::query()->where('name', '🌻')->sole()->displayName())->toBe('Sem nome (🌻)')
        ->and(Prospect::query()->where('name', 'Antigo no WhatsApp')->value('lead_id'))->toBe($lead->id);

    // Reimportar: atualiza sem duplicar e sem mexer na situação.
    $maria->update(['status' => ProspectStatus::Discarded, 'discard_reason' => 'sem_interesse']);
    $again = app(ImportProspects::class)->handle(prospectsCsv([
        ['Maria Souza', '-', '+55 (87) 98888-7777', '-', 'WhatsApp', 'Pipeline Principal', 'Novo Lead', 'Sérgio', 'Aposentadoria rural e BPC', '-', '05/11/2026'],
    ]), 'csv');

    expect($again)->toMatchArray(['created' => 0, 'updated' => 1])
        ->and(Prospect::query()->count())->toBe(3)
        ->and($maria->fresh()->status)->toBe(ProspectStatus::Discarded)
        ->and($maria->fresh()->demand)->toBe('Aposentadoria rural e BPC')
        ->and($maria->fresh()->c2_owner)->toBe('Sérgio');
});

it('recusa planilha sem as colunas de nome e telefone', function () {
    $path = tempnam(sys_get_temp_dir(), 'x').'.csv';
    file_put_contents($path, "Coluna;Outra\n1;2");

    app(ImportProspects::class)->handle($path, 'csv');
})->throws(ValidationException::class);

it('importa pela tela, distribui e descarta', function () {
    // O Livewire descarta upload temporário "antigo": aqui o relógio não pode estar adiantado.
    $this->travelBack();
    Storage::fake('local');
    $manager = userWithRole(Role::Manager);
    $exec = Executive::factory()->create();

    $file = new File('leads.csv', fopen(prospectsCsv([
        ['Ana Lima', '-', '5587911112222', '-', 'WhatsApp', 'Pipeline Principal', 'Novo Lead', '-', 'Pensão por morte', '-', '18/11/2026'],
        ['Bruno Dias', '-', '5587933334444', '-', 'WhatsApp', 'Pipeline Principal', 'Novo Lead', '-', '-', '-', '19/11/2026'],
    ]), 'r'));

    $page = Livewire::actingAs($manager)->test(Index::class)
        ->set('file', $file)->call('import')
        ->assertHasNoErrors()
        ->assertSee('Ana Lima')->assertSee('Pensão por morte')->assertSee('(87) 91111-2222');

    $ana = Prospect::query()->where('name', 'Ana Lima')->sole();
    $bruno = Prospect::query()->where('name', 'Bruno Dias')->sole();

    $page->set('selected', [$ana->id])->set('assignTo', (string) $exec->id)->call('assign');
    expect($ana->fresh()->executive_id)->toBe($exec->id)->and($bruno->fresh()->executive_id)->toBeNull();

    $page->call('confirmDiscard', $bruno->id)->set('discardReason', 'sem_interesse')->call('discard')->assertHasNoErrors();
    expect($bruno->fresh()->status)->toBe(ProspectStatus::Discarded);

    $page->set('executive', 'nenhum')->assertDontSee('Ana Lima');
});

it('qualifica o interessado: vira contato e oportunidade no funil', function () {
    $user = userWithRole(Role::Executive);
    $exec = Executive::factory()->create(['user_id' => $user->id]);
    $prospect = Prospect::query()->create([
        'name' => 'Carla Nunes', 'phone' => '87955556666', 'phone_key' => '8755556666', 'origin' => 'WhatsApp',
        'demand' => 'BPC para o filho', 'entered_at' => '2026-11-10', 'executive_id' => $exec->id,
    ]);
    $other = Prospect::query()->create(['name' => 'De outro', 'phone' => '87900001111', 'phone_key' => '8700001111', 'entered_at' => '2026-11-10']);

    Livewire::actingAs($user)->test(Index::class)->assertSee('Carla Nunes')->assertDontSee('De outro');

    Livewire::actingAs($user)->test(LeadForm::class)
        ->dispatch('lead-form:from-prospect', id: $prospect->id)
        ->assertSet('form.name', 'Carla Nunes')
        ->assertSet('form.whatsapp', '(87) 95555-6666')
        ->assertSet('form.lead_source_id', LeadSource::query()->where('name', 'WhatsApp')->value('id'))
        ->assertSet('opportunity.interest', 'BPC para o filho')
        ->call('save')
        ->assertHasNoErrors();

    $lead = Lead::query()->where('name', 'Carla Nunes')->sole();
    expect($prospect->fresh()->status)->toBe(ProspectStatus::Qualified)
        ->and($prospect->fresh()->lead_id)->toBe($lead->id)
        ->and($lead->notes)->toContain('Interessado pelo WhatsApp desde 10/11/2026')
        ->and(Opportunity::query()->where('lead_id', $lead->id)->value('stage'))->toBe(OpportunityStage::Contact);

    // Não pode qualificar o interessado de outro executivo.
    Livewire::actingAs($user)->test(LeadForm::class)->dispatch('lead-form:from-prospect', id: $other->id)->assertForbidden();
});

it('mostra o menu Interessados e dá as permissões novas aos perfis existentes', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $gestor = RoleModel::findByName(Role::Manager->value);
    $gestor->revokePermissionTo('prospects.manage');
    Permission::findByName('prospects.manage')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Permissão que ainda não existia: entra no perfil que a tem como padrão.
    $this->seed(RolesAndPermissionsSeeder::class);
    expect($gestor->fresh()->hasPermissionTo('prospects.manage'))->toBeTrue();

    $manager = userWithRole(Role::Manager);
    $labels = collect(Navigation::for($manager))->flatMap(fn ($g) => array_column($g['items'], 'label'));
    expect($labels)->toContain('Interessados');
    $this->actingAs($manager)->get(route('prospects.index'))->assertOk();
    $this->actingAs(userWithRole(Role::Indicator))->get(route('prospects.index'))->assertForbidden();
});
