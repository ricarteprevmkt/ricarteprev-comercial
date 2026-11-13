<?php

use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio')->name('home');

Route::middleware(['auth'])->group(function () {
    // Destino após o login: o primeiro módulo que o perfil do usuário pode abrir.
    Route::get('inicio', fn () => redirect()->route(Navigation::homeRouteFor(auth()->user())))->name('start');

    // Módulos comerciais. As telas são entregues fase a fase; enquanto isso,
    // cada rota mostra a fase em que o módulo será liberado.
    $pending = fn (string $uri, string $name, string $title, string $subtitle, string $phase, string $abilities) => Route::view($uri, 'modules.pending', compact('title', 'subtitle', 'phase'))
        ->middleware("can.any:{$abilities}")
        ->name($name);

    $pending('visao-geral', 'dashboard', 'Visão geral', 'Acompanhe o desempenho comercial da equipe e o andamento das oportunidades.', 'Fase 7', 'dashboard.view,dashboard.view_own');
    $pending('executivos', 'executives.index', 'Executivos', 'Desempenho individual e comparativo da equipe comercial.', 'Fases 2 e 7', 'executives.view');
    $pending('agenda', 'agenda.index', 'Agenda e rotas', 'Planejamento diário, visitas, deslocamentos e acompanhamento da equipe em campo.', 'Fase 4', 'agenda.view_all,agenda.view_own');
    $pending('funil', 'funnel.index', 'Funil comercial', 'Fluxo de oportunidades e taxa de conversão da equipe comercial.', 'Fase 3', 'funnel.view_all,funnel.view_own');
    $pending('custos', 'finance.index', 'Custos e resultados', 'Eficiência da operação comercial e retorno sobre o investimento.', 'Fase 6', 'finance.view,expenses.view_own');
    $pending('indicadores-regionais', 'referrals.index', 'Indicadores regionais', 'Indicações por região e impacto das parcerias locais.', 'Fase 5', 'referrals.view_all,referrals.view_own');
    $pending('relatorios', 'reports.index', 'Relatórios', 'Relatórios com filtros e exportação em PDF e Excel.', 'Fase 8', 'reports.view');

    Route::prefix('admin')->name('admin.')->group(function () use ($pending) {
        $pending('usuarios', 'users', 'Usuários', 'Cadastro, perfil e status de acesso dos usuários.', 'Fase 2', 'users.manage');
        $pending('perfis', 'roles', 'Perfis e permissões', 'O que cada perfil pode ver e fazer no sistema.', 'Fase 2', 'roles.manage');
        $pending('regioes', 'regions', 'Regiões e cidades', 'Regiões comerciais e municípios atendidos.', 'Fase 2', 'regions.manage');
        $pending('parametros', 'settings', 'Parâmetros', 'Fator de estrada, custo por km, prazos de alerta e outras regras comerciais.', 'Fase 2', 'settings.manage');
        $pending('auditoria', 'audit', 'Auditoria', 'Histórico de alterações: quem fez, o quê e quando.', 'Fase 2', 'audit.view');
    });
});

require __DIR__.'/settings.php';
