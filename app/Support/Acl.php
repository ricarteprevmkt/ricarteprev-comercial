<?php

namespace App\Support;

/**
 * Catálogo de permissões agrupadas por módulo e as permissões padrão de cada
 * perfil. O seeder cria tudo o que faltar, mas só aplica os padrões a perfis
 * recém-criados: depois disso, o administrador ajusta pela tela de perfis e o
 * seeder não sobrescreve as escolhas dele.
 *
 * O perfil "administrador" recebe acesso total por Gate::before.
 */
final class Acl
{
    /**
     * @return array<string, array<string, string>> módulo => [permissão => descrição]
     */
    public static function permissions(): array
    {
        return [
            'Visão geral' => [
                'dashboard.view' => 'Ver a visão geral da equipe',
                'dashboard.view_own' => 'Ver o próprio desempenho',
            ],
            'Executivos' => [
                'executives.view' => 'Ver desempenho de todos os executivos',
                'executives.manage' => 'Cadastrar e editar executivos',
                'goals.manage' => 'Definir metas',
            ],
            'Agenda e rotas' => [
                'agenda.view_all' => 'Ver a agenda de toda a equipe',
                'agenda.view_own' => 'Ver a própria agenda',
                'agenda.manage' => 'Criar e atualizar visitas',
            ],
            'Funil comercial' => [
                'funnel.view_all' => 'Ver todas as oportunidades',
                'funnel.view_own' => 'Ver as próprias oportunidades',
                'leads.manage' => 'Cadastrar contatos e mover oportunidades',
                'contracts.manage' => 'Registrar contratos',
            ],
            'Custos e resultados' => [
                'finance.view' => 'Ver custos e resultados da operação',
                'expenses.view_own' => 'Ver as próprias despesas',
                'expenses.manage' => 'Lançar despesas',
                'expenses.approve' => 'Aprovar despesas',
            ],
            'Indicadores regionais' => [
                'referrals.view_all' => 'Ver todas as indicações',
                'referrals.view_own' => 'Ver as próprias indicações',
                'referrals.create' => 'Registrar indicações',
                'referrals.performance_own' => 'Ver o próprio desempenho como indicador',
                'indicators.manage' => 'Cadastrar indicadores regionais',
                'commissions.manage' => 'Gerenciar comissões',
                'commissions.view_own' => 'Ver as próprias comissões',
            ],
            'Relatórios' => [
                'reports.view' => 'Ver relatórios',
                'reports.export' => 'Exportar relatórios em PDF e Excel',
            ],
            'Administração' => [
                'users.manage' => 'Gerenciar usuários',
                'roles.manage' => 'Configurar permissões dos perfis',
                'regions.manage' => 'Cadastrar regiões e cidades atendidas',
                'settings.manage' => 'Alterar parâmetros comerciais',
                'audit.view' => 'Consultar auditoria',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        $names = [];

        foreach (self::permissions() as $permissions) {
            foreach ($permissions as $name => $description) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return array<string, list<string>> perfil => permissões padrão
     */
    public static function defaults(): array
    {
        return [
            'gestor' => [
                'dashboard.view', 'dashboard.view_own',
                'executives.view', 'goals.manage',
                'agenda.view_all', 'agenda.view_own', 'agenda.manage',
                'funnel.view_all', 'funnel.view_own', 'leads.manage', 'contracts.manage',
                'finance.view', 'expenses.view_own', 'expenses.manage', 'expenses.approve',
                'referrals.view_all', 'referrals.create', 'indicators.manage', 'commissions.manage',
                'reports.view', 'reports.export',
            ],
            'executivo' => [
                'dashboard.view_own',
                'agenda.view_own', 'agenda.manage',
                'funnel.view_own', 'leads.manage', 'contracts.manage',
                'expenses.view_own', 'expenses.manage',
                'referrals.create',
            ],
            'indicador' => [
                'referrals.view_own', 'referrals.create',
            ],
        ];
    }
}
