<?php

namespace App\Actions\Notifications;

use App\Actions\C2\SyncC2Processes;
use App\Enums\ContractStatus;
use App\Enums\ExecutiveStatus;
use App\Enums\ExpenseStatus;
use App\Enums\OpportunityStage;
use App\Enums\VisitStatus;
use App\Metrics\ExecutivePerformance;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\IntegrationState;
use App\Models\Opportunity;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\SystemAlert;
use App\Services\C2\C2Client;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gera os avisos internos do sistema (sino). Cada aviso tem uma chave e não é
 * enviado de novo para a mesma pessoa no mesmo dia.
 *
 * - upcomingVisits(): a cada 15 min, visitas que começam na próxima hora.
 * - daily(): todo dia de manhã, resumo das pendências de cada pessoa.
 */
final class SendAlerts
{
    private int $sent = 0;

    /** Visitas que começam na próxima hora: avisa o executivo, uma vez por visita. */
    public function upcomingVisits(): int
    {
        $this->sent = 0;

        Visit::query()
            ->with(['executive.user', 'lead', 'city'])
            ->whereIn('status', [VisitStatus::Scheduled->value, VisitStatus::Confirmed->value])
            ->whereBetween('scheduled_at', [now(), now()->addHour()])
            ->get()
            ->each(fn (Visit $v) => $this->notify(
                $v->executive->user,
                "visit-upcoming-{$v->id}",
                'Visita às '.$v->scheduled_at->format('H:i').': '.$v->displayName().($v->city_id ? ' ('.$v->city?->name.')' : ''),
                route('agenda.index', ['dia' => $v->scheduled_at->toDateString()]),
                perDay: false,
            ));

        return $this->sent;
    }

    /** Resumo diário de pendências para executivos e para quem acompanha a equipe. */
    public function daily(): int
    {
        $this->sent = 0;
        $proposalDays = (int) SystemSetting::get('funnel.proposal_followup_days', 5);

        $executives = Executive::query()->with('user')->where('status', '!=', ExecutiveStatus::Terminated->value)->get();
        foreach ($executives as $e) {
            if (! $e->user->active) {
                continue;
            }
            $this->countAlert($e->user, 'visits-without-result', Visit::query()->where('executive_id', $e->id)->withoutResult()->count(),
                'visita(s) sem resultado registrado', route('agenda.index'));
            $this->countAlert($e->user, 'overdue', Opportunity::query()->where('executive_id', $e->id)->overdue()->count(),
                'oportunidade(s) com prazo vencido', route('funnel.opportunities', ['etapa' => 'atrasadas']));
            $this->countAlert($e->user, 'stale', Opportunity::query()->where('executive_id', $e->id)->stale()->count(),
                'oportunidade(s) sem atualização', route('funnel.opportunities', ['etapa' => 'paradas']));
            $this->countAlert($e->user, 'proposals-waiting', $this->proposalsWaiting($proposalDays)->where('executive_id', $e->id)->count(),
                "proposta(s) aguardando retorno há mais de {$proposalDays} dias", route('funnel.opportunities', ['etapa' => 'proposal']));
        }

        // Contratos assinados que ainda não aparecem no C2 (só depois da carga inicial da integração).
        $c2Days = (int) SystemSetting::get('c2.registration_days', 2);
        $checkC2 = C2Client::enabled() && IntegrationState::get(SyncC2Processes::BACKFILL_DONE, false);
        $c2Text = "contrato(s) assinado(s) há mais de {$c2Days} dia(s) sem cadastro no C2";
        $c2Url = route('funnel.contracts', ['inss' => 'sem-c2']);

        if ($checkC2) {
            foreach ($executives as $e) {
                if ($e->user->active) {
                    $this->countAlert($e->user, 'missing-in-c2', Contract::query()->where('executive_id', $e->id)->missingInC2($c2Days)->count(), $c2Text, $c2Url);
                }
            }
        }

        $team = User::query()->active()->get()->filter(fn (User $u) => $u->can('funnel.view_all'));
        $belowGoal = count((new ExecutivePerformance(Period::fromPreset('mes_atual')))->belowGoal());

        foreach ($team as $user) {
            $this->countAlert($user, 'team-pending-contracts', Contract::query()->where('status', ContractStatus::Pending->value)->count(),
                'contrato(s) aguardando conclusão', route('funnel.contracts', ['situacao' => 'pending']));
            if ($checkC2) {
                $this->countAlert($user, 'team-missing-in-c2', Contract::query()->missingInC2($c2Days)->count(), $c2Text, $c2Url);
            }
            $this->countAlert($user, 'team-not-done-yesterday', Visit::query()->where('status', VisitStatus::NotDone->value)
                ->whereBetween('scheduled_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])->count(),
                'visita(s) não realizada(s) ontem', route('agenda.index', ['dia' => now()->subDay()->toDateString()]));
            if ($user->can('executives.view')) {
                $this->countAlert($user, 'team-below-goal', $belowGoal, 'executivo(s) abaixo do ritmo da meta do mês', route('executives.index'));
            }
            if ($user->can('expenses.approve')) {
                $this->countAlert($user, 'team-pending-expenses', Expense::query()->where('status', ExpenseStatus::Pending->value)->count(),
                    'despesa(s) aguardando aprovação', route('finance.expenses', ['situacao' => 'pending']));
            }
        }

        return $this->sent;
    }

    /** @return Builder<Opportunity> */
    private function proposalsWaiting(int $days): Builder
    {
        return Opportunity::query()->where('stage', OpportunityStage::Proposal->value)->where('stage_changed_at', '<', now()->subDays($days));
    }

    private function countAlert(User $user, string $key, int $count, string $text, string $url): void
    {
        if ($count > 0) {
            $this->notify($user, $key, "{$count} {$text}", $url);
        }
    }

    private function notify(User $user, string $key, string $message, string $url, bool $perDay = true): void
    {
        $fullKey = $perDay ? $key.'-'.now()->toDateString() : $key;

        if ($user->notifications()->where('data->key', $fullKey)->exists()) {
            return;
        }

        $user->notify(new SystemAlert($fullKey, $message, $url));
        $this->sent++;
    }
}
