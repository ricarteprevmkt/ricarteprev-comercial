<?php

namespace App\Metrics;

use App\Enums\ContractStatus;
use App\Enums\ExpenseStatus;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\Visit;
use App\Support\Period;
use App\Support\Visibility;

/**
 * Alertas e pendências que precisam de ação, já recortados pelo que o
 * usuário pode ver. Cada alerta aponta para a lista correspondente.
 */
final class Alerts
{
    /**
     * @return list<array{key: string, label: string, count: int, url: string, tone: string}>
     */
    public static function for(User $user): array
    {
        $scope = Visibility::executiveIds($user);
        $scoped = fn ($query) => $scope === null ? $query : $query->whereIn($query->getModel()->qualifyColumn('executive_id'), $scope ?: [0]);
        $alerts = [];

        if ($user->can('funnel.view_all') || $user->can('funnel.view_own')) {
            $alerts[] = ['key' => 'overdue', 'label' => 'Prazos vencidos', 'tone' => 'danger',
                'count' => $scoped(Opportunity::query())->overdue()->count(),
                'url' => route('funnel.opportunities', ['etapa' => 'atrasadas'])];
            $alerts[] = ['key' => 'stale', 'label' => 'Oportunidades sem atualização', 'tone' => 'warning',
                'count' => $scoped(Opportunity::query())->stale()->count(),
                'url' => route('funnel.opportunities', ['etapa' => 'paradas'])];
            $alerts[] = ['key' => 'pending_contracts', 'label' => 'Contratos aguardando conclusão', 'tone' => 'warning',
                'count' => $scoped(Contract::query())->where('status', ContractStatus::Pending->value)->count(),
                'url' => route('funnel.contracts', ['situacao' => 'pending'])];
        }

        if ($user->can('agenda.view_all') || $user->can('agenda.view_own')) {
            $alerts[] = ['key' => 'visits_without_result', 'label' => 'Visitas sem resultado registrado', 'tone' => 'warning',
                'count' => $scoped(Visit::query())->withoutResult()->count(),
                'url' => route('agenda.index')];
        }

        if ($user->can('dashboard.view') && $user->can('executives.view')) {
            $alerts[] = ['key' => 'below_goal', 'label' => 'Executivos abaixo da meta', 'tone' => 'info',
                'count' => count((new ExecutivePerformance(Period::fromPreset('mes_atual')))->belowGoal($scope)),
                'url' => route('executives.index')];
        }

        if ($user->can('expenses.approve')) {
            $alerts[] = ['key' => 'pending_expenses', 'label' => 'Despesas aguardando aprovação', 'tone' => 'info',
                'count' => Expense::query()->where('status', ExpenseStatus::Pending->value)->count(),
                'url' => route('finance.expenses', ['situacao' => 'pending'])];
        }

        return $alerts;
    }
}
