<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\PhotoController;
use App\Livewire\Admin;
use App\Livewire\Agenda;
use App\Livewire\Executives;
use App\Livewire\Finance;
use App\Livewire\Funnel;
use App\Livewire\Referrals;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio')->name('home');

Route::middleware(['auth'])->group(function () {
    // Destino após o login: o primeiro módulo que o perfil do usuário pode abrir.
    Route::get('inicio', fn () => redirect()->route(Navigation::homeRouteFor(auth()->user())))->name('start');

    // Módulos ainda não construídos mostram a fase em que serão liberados.
    $pending = fn (string $uri, string $name, string $title, string $subtitle, string $phase, string $abilities) => Route::view($uri, 'modules.pending', compact('title', 'subtitle', 'phase'))
        ->middleware("can.any:{$abilities}")
        ->name($name);

    $pending('visao-geral', 'dashboard', 'Visão geral', 'Acompanhe o desempenho comercial da equipe e o andamento das oportunidades.', 'Fase 7', 'dashboard.view,dashboard.view_own');
    $pending('relatorios', 'reports.index', 'Relatórios', 'Relatórios com filtros e exportação em PDF e Excel.', 'Fase 8', 'reports.view');

    // Fase 3: CRM e funil comercial. O recorte por executivo é feito nas policies.
    Route::prefix('funil')->name('funnel.')->middleware('can.any:funnel.view_all,funnel.view_own')->group(function () {
        Route::livewire('/', Funnel\Dashboard::class)->name('index');
        Route::livewire('oportunidades', Funnel\Opportunities::class)->name('opportunities');
        Route::livewire('oportunidades/{opportunity}', Funnel\OpportunityShow::class)->name('opportunities.show');
        Route::livewire('contatos', Funnel\Leads::class)->name('leads');
        Route::livewire('contratos', Funnel\Contracts::class)->name('contracts');
    });

    // Fase 4: agenda e rotas.
    Route::livewire('agenda', Agenda\Index::class)->middleware('can.any:agenda.view_all,agenda.view_own')->name('agenda.index');

    // Fase 2: cadastros.
    Route::livewire('executivos', Executives\Index::class)->middleware('can.any:executives.view')->name('executives.index');
    // Fase 6: custos e resultados. Quem só vê as próprias despesas vai direto para a lista.
    Route::get('custos', fn () => auth()->user()->can('finance.view')
        ? redirect()->route('finance.dashboard')
        : redirect()->route('finance.expenses'))->middleware('can.any:finance.view,expenses.view_own')->name('finance.index');
    Route::livewire('custos/painel', Finance\Dashboard::class)->middleware('can.any:finance.view')->name('finance.dashboard');
    Route::livewire('custos/despesas', Finance\Expenses::class)->middleware('can.any:finance.view,expenses.view_own')->name('finance.expenses');

    // Fase 5: indicadores regionais.
    Route::livewire('indicadores-regionais', Referrals\Dashboard::class)->middleware('can.any:referrals.view_all')->name('referrals.index');
    Route::livewire('indicadores-regionais/indicacoes', Referrals\ReferralList::class)->middleware('can.any:referrals.view_all,referrals.create')->name('referrals.list');
    Route::livewire('indicadores-regionais/comissoes', Referrals\CommissionsIndex::class)->middleware('can.any:commissions.manage')->name('referrals.commissions');
    Route::livewire('indicadores-regionais/indicadores/{indicator}', Referrals\IndicatorShow::class)->middleware('can.any:indicators.manage,referrals.view_all')->name('referrals.indicators.show');
    Route::livewire('minhas-indicacoes', Referrals\Portal::class)->middleware('can.any:referrals.view_own')->name('referrals.mine');
    Route::livewire('indicadores-regionais/cadastro', Referrals\Indicators::class)->middleware('can.any:indicators.manage')->name('referrals.indicators');
    Route::livewire('indicadores-regionais/regras-de-comissao', Referrals\CommissionRules::class)->middleware('can.any:commissions.manage')->name('referrals.commission-rules');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('usuarios', Admin\Users::class)->middleware('can.any:users.manage')->name('users');
        Route::livewire('perfis', Admin\RolePermissions::class)->middleware('can.any:roles.manage')->name('roles');
        Route::livewire('regioes', Admin\RegionsCities::class)->middleware('can.any:regions.manage')->name('regions');
        Route::livewire('catalogos', Admin\Catalogs::class)->middleware('can.any:settings.manage')->name('catalogs');
        Route::livewire('parametros', Admin\Settings::class)->middleware('can.any:settings.manage')->name('settings');
        Route::livewire('auditoria', Admin\Audit::class)->middleware('can.any:audit.view')->name('audit');
    });

    // Fotos ficam no disco privado; a autorização é feita no controller.
    Route::get('fotos/executivos/{executive}', [PhotoController::class, 'executive'])->name('photos.executive');
    Route::get('fotos/indicadores/{indicator}', [PhotoController::class, 'indicator'])->name('photos.indicator');
    Route::get('anexos/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
});

require __DIR__.'/settings.php';
