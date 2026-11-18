<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Admin;
use App\Livewire\Administrative\Dashboard;
use App\Livewire\Administrative\Deadlines;
use App\Livewire\Administrative\Pending;
use App\Livewire\Administrative\Processes;
use App\Livewire\Administrative\ProcessShow;
use App\Livewire\Agenda;
use App\Livewire\Executives;
use App\Livewire\Finance;
use App\Livewire\Funnel;
use App\Livewire\Overview;
use App\Livewire\Prospects;
use App\Livewire\Referrals;
use App\Livewire\Reports;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio')->name('home');

Route::middleware(['auth'])->group(function () {
    // Destino após o login: o primeiro módulo que o perfil do usuário pode abrir.
    Route::get('inicio', fn () => redirect()->route(Navigation::homeRouteFor(auth()->user())))->name('start');

    // Fase 8: relatórios.
    Route::livewire('relatorios', Reports\Index::class)->middleware('can.any:reports.view')->name('reports.index');
    Route::get('relatorios/{report}/exportar/{format}', ReportExportController::class)->middleware('can.any:reports.export')->name('reports.export');

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

    // Fase 7: painéis.
    Route::livewire('visao-geral', Overview::class)->middleware('can.any:dashboard.view,dashboard.view_own')->name('dashboard');
    Route::livewire('executivos/{executive}', Executives\Show::class)->middleware('can.any:executives.view,dashboard.view_own')->name('executives.show');

    // Fase 2: cadastros.
    Route::livewire('executivos', Executives\Index::class)->middleware('can.any:executives.view')->name('executives.index');
    // Fase 6: custos e resultados. Quem só vê as próprias despesas vai direto para a lista.
    Route::get('custos', fn () => auth()->user()->can('finance.view')
        ? redirect()->route('finance.dashboard')
        : redirect()->route('finance.expenses'))->middleware('can.any:finance.view,expenses.view_own')->name('finance.index');
    Route::livewire('custos/painel', Finance\Dashboard::class)->middleware('can.any:finance.view')->name('finance.dashboard');
    Route::livewire('custos/despesas', Finance\Expenses::class)->middleware('can.any:finance.view,expenses.view_own')->name('finance.expenses');

    // Fase 5: parceiros regionais.
    Route::livewire('parceiros-regionais', Referrals\Dashboard::class)->middleware('can.any:referrals.view_all')->name('referrals.index');
    Route::livewire('parceiros-regionais/indicacoes', Referrals\ReferralList::class)->middleware('can.any:referrals.view_all,referrals.create')->name('referrals.list');
    Route::livewire('parceiros-regionais/comissoes', Referrals\CommissionsIndex::class)->middleware('can.any:commissions.manage')->name('referrals.commissions');
    Route::livewire('parceiros-regionais/parceiros/{indicator}', Referrals\IndicatorShow::class)->middleware('can.any:indicators.manage,referrals.view_all')->name('referrals.indicators.show');
    Route::livewire('minhas-indicacoes', Referrals\Portal::class)->middleware('can.any:referrals.view_own')->name('referrals.mine');
    Route::livewire('parceiros-regionais/cadastro', Referrals\Indicators::class)->middleware('can.any:indicators.manage')->name('referrals.indicators');
    Route::livewire('parceiros-regionais/regras-de-comissao', Referrals\CommissionRules::class)->middleware('can.any:commissions.manage')->name('referrals.commission-rules');

    // Interessados (WhatsApp do C2), antes do funil.
    Route::livewire('interessados', Prospects\Index::class)->middleware('can.any:prospects.view_all,prospects.view_own')->name('prospects.index');

    // Administrativo: processos no INSS lidos do C2 Prev (só consulta).
    Route::prefix('administrativo')->name('adm.')->middleware('can.any:inss.view')->group(function () {
        Route::livewire('/', Dashboard::class)->name('index');
        Route::livewire('processos', Processes::class)->name('processes');
        Route::livewire('processos/{process}', ProcessShow::class)->name('processes.show');
        Route::livewire('prazos', Deadlines::class)->name('deadlines');
        Route::livewire('pendencias', Pending::class)->name('pending');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('usuarios', Admin\Users::class)->middleware('can.any:users.manage')->name('users');
        Route::livewire('perfis', Admin\RolePermissions::class)->middleware('can.any:roles.manage')->name('roles');
        Route::livewire('regioes', Admin\RegionsCities::class)->middleware('can.any:regions.manage')->name('regions');
        Route::livewire('catalogos', Admin\Catalogs::class)->middleware('can.any:settings.manage')->name('catalogs');
        Route::livewire('parametros', Admin\Settings::class)->middleware('can.any:settings.manage')->name('settings');
        Route::livewire('integracao-c2', Admin\C2Integration::class)->middleware('can.any:settings.manage')->name('c2');
        Route::livewire('auditoria', Admin\Audit::class)->middleware('can.any:audit.view')->name('audit');
    });

    // Fotos ficam no disco privado; a autorização é feita no controller.
    Route::get('fotos/executivos/{executive}', [PhotoController::class, 'executive'])->name('photos.executive');
    Route::get('fotos/parceiros/{indicator}', [PhotoController::class, 'indicator'])->name('photos.indicator');
    Route::get('anexos/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
});

require __DIR__.'/settings.php';
