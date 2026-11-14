<?php

use App\Enums\ExpenseStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Livewire\Agenda\ExpenseForm;
use App\Metrics\AgendaMetrics;
use App\Models\Attachment;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\Region;
use App\Models\Route;
use App\Models\SystemSetting;
use App\Models\Visit;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    // Sem viagem no tempo aqui: o arquivo temporário de upload do Livewire expira.
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->region = Region::create(['name' => 'Cariri']);
    $this->exec = Executive::factory()->create(['region_id' => $this->region->id]);
    $this->exec->user->assignRole(userWithRole(Role::Executive)->getRoleNames()->first());
    $this->fuel = ExpenseCategory::where('slug', 'combustivel-e-deslocamento')->firstOrFail();
});

it('lança despesa com comprovante no disco privado, aguardando aprovação', function () {
    Storage::fake('local');

    Livewire::actingAs($this->exec->user)->test(ExpenseForm::class)
        ->call('create')
        ->set('form.description', 'Abastecimento')
        ->set('form.expense_category_id', $this->fuel->id)
        ->set('form.amount', '150.75')
        ->set('receipt', UploadedFile::fake()->image('nota.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $expense = Expense::with('attachments')->firstOrFail();
    expect($expense->status)->toBe(ExpenseStatus::Pending)
        ->and($expense->executive_id)->toBe($this->exec->id)
        ->and($expense->region_id)->toBe($this->region->id)
        ->and($expense->amount)->toBe('150.75')
        ->and($expense->attachments)->toHaveCount(1);

    $attachment = $expense->attachments->first();
    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($this->exec->user)->get(route('attachments.show', $attachment))->assertOk();
    $this->actingAs(userWithRole(Role::Executive))->get(route('attachments.show', $attachment))->assertForbidden();
    $this->actingAs(userWithRole(Role::Manager))->get(route('attachments.show', $attachment))->assertOk();
});

it('o executivo não lança despesa em nome de outro e não pode datar no futuro', function () {
    $other = Executive::factory()->create();

    Livewire::actingAs($this->exec->user)->test(ExpenseForm::class)
        ->call('create')
        ->set('form.description', 'X')
        ->set('form.expense_category_id', $this->fuel->id)
        ->set('form.amount', '10')
        ->set('form.executive_id', $other->id)
        ->set('form.spent_on', '2026-12-01')
        ->call('save')
        ->assertHasErrors(['form.executive_id', 'form.spent_on']);
});

it('resume o dia separando estimado de registrado e ignorando despesas recusadas', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 18:00:00'));
    SystemSetting::put('routes.cost_per_km', '0.80');
    $day = CarbonImmutable::parse('2026-11-16');
    $base = ['executive_id' => $this->exec->id];

    Visit::factory()->create($base + ['status' => VisitStatus::Done, 'scheduled_at' => '2026-11-16 08:00', 'km_reported' => '10.0']);
    Visit::factory()->create($base + ['status' => VisitStatus::Scheduled, 'scheduled_at' => '2026-11-16 10:00']);
    Visit::factory()->create($base + ['status' => VisitStatus::NotDone, 'scheduled_at' => '2026-11-16 11:00']);
    Visit::factory()->create($base + ['status' => VisitStatus::Cancelled, 'scheduled_at' => '2026-11-16 12:00']);
    Route::create(['executive_id' => $this->exec->id, 'date' => '2026-11-16', 'km_estimated' => '25.0', 'stops_count' => 3]);

    $make = fn (string $amount, ExpenseStatus $status) => tap(new Expense([
        'description' => 'x', 'expense_category_id' => $this->fuel->id, 'amount' => $amount, 'spent_on' => '2026-11-16', 'executive_id' => $this->exec->id,
    ]), fn ($e) => $e->forceFill(['status' => $status])->save());
    $make('40.00', ExpenseStatus::Pending);
    $make('60.00', ExpenseStatus::Approved);
    $make('999.00', ExpenseStatus::Rejected);

    $s = AgendaMetrics::day($day, [$this->exec->id])->summary();

    expect($s['planned'])->toBe(3)
        ->and($s['done'])->toBe(1)
        ->and($s['pending'])->toBe(1)
        ->and($s['not_done'])->toBe(1)
        ->and($s['km_estimated'])->toBe(25.0)
        ->and($s['km_reported'])->toBe(10.0)
        ->and($s['cost_estimated'])->toBe('20.00')
        ->and($s['expenses_actual'])->toBe('100.00');
});

it('calcula distância por estrada com o fator configurado', function () {
    // Juazeiro do Norte → Crato: cerca de 10 km em linha reta.
    $straight = Geo::straightKm(-7.19621, -39.3076, -7.2153, -39.4103);

    expect($straight)->toBeGreaterThan(10.0)->toBeLessThan(12.5)
        ->and(Geo::roadKm(-7.19621, -39.3076, -7.2153, -39.4103, 1.3))->toBe(round($straight * 1.3, 1))
        ->and(Attachment::count())->toBe(0);
});
