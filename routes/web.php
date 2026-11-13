<?php

use App\Http\Controllers\PhotoController;
use App\Livewire\Admin;
use App\Livewire\Executives;
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
    $pending('agenda', 'agenda.index', 'Agenda e rotas', 'Planejamento diário, visitas, deslocamentos e acompanhamento da equipe em campo.', 'Fase 4', 'agenda.view_all,agenda.view_own');
    $pending('funil', 'funnel.index', 'Funil comercial', 'Fluxo de oportunidades e taxa de conversão da equipe comercial.', 'Fase 3', 'funnel.view_all,funnel.view_own');
    $pending('custos', 'finance.index', 'Custos e resultados', 'Eficiência da operação comercial e retorno sobre o investimento.', 'Fase 6', 'finance.view,expenses.view_own');
    $pending('indicadores-regionais', 'referrals.index', 'Indicadores regionais', 'Indicações por região e impacto das parcerias locais.', 'Fase 5', 'referrals.view_all,referrals.view_own');
    $pending('relatorios', 'reports.index', 'Relatórios', 'Relatórios com filtros e exportação em PDF e Excel.', 'Fase 8', 'reports.view');

    // Fase 2: cadastros.
    Route::livewire('executivos', Executives\Index::class)->middleware('can.any:executives.view')->name('executives.index');
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
});

require __DIR__.'/settings.php';
