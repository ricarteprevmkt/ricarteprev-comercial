<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Itens do menu lateral. A mesma lista define o que aparece para cada perfil
 * e para onde o usuário vai depois do login (o primeiro item a que tem acesso).
 */
final class Navigation
{
    /**
     * @return list<array{group: string, items: list<array{label: string, route: string, icon: string, abilities: list<string>, active?: string|list<string>, requires_executive?: bool, hidden_for?: string}>}>
     */
    public static function groups(): array
    {
        return [
            ['group' => 'Comercial', 'items' => [
                ['label' => 'Visão geral', 'route' => 'dashboard', 'icon' => 'home', 'abilities' => ['dashboard.view', 'dashboard.view_own']],
                ['label' => 'Executivos', 'route' => 'executives.index', 'icon' => 'user', 'abilities' => ['executives.view'], 'active' => 'executives.*'],
                ['label' => 'Agenda e rotas', 'route' => 'agenda.index', 'icon' => 'calendar-days', 'abilities' => ['agenda.view_all', 'agenda.view_own']],
                ['label' => 'Funil comercial', 'route' => 'funnel.index', 'icon' => 'funnel', 'abilities' => ['funnel.view_all', 'funnel.view_own'], 'active' => 'funnel.*'],
                ['label' => 'Interessados', 'route' => 'prospects.index', 'icon' => 'chat-bubble-left-right', 'abilities' => ['prospects.view_all', 'prospects.view_own']],
                ['label' => 'Custos e resultados', 'route' => 'finance.index', 'icon' => 'chart-bar', 'abilities' => ['finance.view', 'expenses.view_own'], 'active' => 'finance.*'],
                // Equipe com visão geral: painel regional. Executivo: lista das indicações que atende.
                ['label' => 'Parceiros regionais', 'route' => 'referrals.index', 'icon' => 'map-pin', 'abilities' => ['referrals.view_all'], 'active' => ['referrals.index', 'referrals.list', 'referrals.commissions', 'referrals.indicators.show']],
                ['label' => 'Indicações', 'route' => 'referrals.list', 'icon' => 'map-pin', 'abilities' => ['referrals.create'], 'requires_executive' => true],
                ['label' => 'Minhas indicações', 'route' => 'referrals.mine', 'icon' => 'map-pin', 'abilities' => ['referrals.view_own'], 'hidden_for' => 'referrals.view_all'],
                ['label' => 'Relatórios', 'route' => 'reports.index', 'icon' => 'document-chart-bar', 'abilities' => ['reports.view']],
            ]],
            ['group' => 'Administrativo', 'items' => [
                ['label' => 'Painel', 'route' => 'adm.index', 'icon' => 'building-library', 'abilities' => ['inss.view']],
                ['label' => 'Processos no INSS', 'route' => 'adm.processes', 'icon' => 'document-text', 'abilities' => ['inss.view'], 'active' => 'adm.processes*'],
                ['label' => 'Prazos', 'route' => 'adm.deadlines', 'icon' => 'calendar', 'abilities' => ['inss.view']],
                ['label' => 'Pendências', 'route' => 'adm.pending', 'icon' => 'exclamation-triangle', 'abilities' => ['inss.view']],
            ]],
            ['group' => 'Cadastros', 'items' => [
                ['label' => 'Parceiros', 'route' => 'referrals.indicators', 'icon' => 'user-group', 'abilities' => ['indicators.manage']],
                ['label' => 'Regras de comissão', 'route' => 'referrals.commission-rules', 'icon' => 'banknotes', 'abilities' => ['commissions.manage']],
                ['label' => 'Catálogos', 'route' => 'admin.catalogs', 'icon' => 'list-bullet', 'abilities' => ['settings.manage']],
            ]],
            ['group' => 'Administração', 'items' => [
                ['label' => 'Usuários', 'route' => 'admin.users', 'icon' => 'users', 'abilities' => ['users.manage']],
                ['label' => 'Perfis e permissões', 'route' => 'admin.roles', 'icon' => 'shield-check', 'abilities' => ['roles.manage']],
                ['label' => 'Regiões e cidades', 'route' => 'admin.regions', 'icon' => 'map', 'abilities' => ['regions.manage']],
                ['label' => 'Parâmetros', 'route' => 'admin.settings', 'icon' => 'adjustments-horizontal', 'abilities' => ['settings.manage']],
                ['label' => 'Integração C2 Prev', 'route' => 'admin.c2', 'icon' => 'arrow-path', 'abilities' => ['settings.manage']],
                ['label' => 'Auditoria', 'route' => 'admin.audit', 'icon' => 'clipboard-document-list', 'abilities' => ['audit.view']],
            ]],
        ];
    }

    /**
     * @return list<array{group: string, items: list<array{label: string, route: string, icon: string, abilities: list<string>, active?: string|list<string>, requires_executive?: bool, hidden_for?: string}>}>
     */
    public static function for(User $user): array
    {
        $gate = Gate::forUser($user);
        $groups = [];

        foreach (self::groups() as $group) {
            $isExecutive = $user->executive()->exists();
            $seesPanel = $gate->allows('referrals.view_all');
            $items = array_values(array_filter($group['items'], fn (array $item) => $gate->any($item['abilities'])
                && (! ($item['requires_executive'] ?? false) || ($isExecutive && ! $seesPanel))
                && (! isset($item['hidden_for']) || $gate->denies($item['hidden_for']))));

            if ($items !== []) {
                $groups[] = ['group' => $group['group'], 'items' => $items];
            }
        }

        return $groups;
    }

    public static function homeRouteFor(User $user): string
    {
        return self::for($user)[0]['items'][0]['route'] ?? 'profile.edit';
    }
}
