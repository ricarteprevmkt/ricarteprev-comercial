<?php

namespace Database\Seeders;

use App\Actions\Agenda\SaveExpense;
use App\Actions\Agenda\SaveVisit;
use App\Actions\Agenda\VisitWorkflow;
use App\Actions\Crm\MoveOpportunity;
use App\Actions\Crm\RegisterContract;
use App\Actions\Crm\RegisterProposal;
use App\Actions\Crm\SaveLead;
use App\Actions\Finance\ReviewExpense;
use App\Actions\Referrals\Commissions;
use App\Actions\Referrals\RegisterReferral;
use App\Enums\ContractStatus;
use App\Enums\ExpenseStatus;
use App\Enums\OpportunityStage;
use App\Enums\Role;
use App\Enums\VisitType;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Catalogs\LeadSource;
use App\Models\Catalogs\LossReason;
use App\Models\Catalogs\ReferralChannel;
use App\Models\Catalogs\VisitReason;
use App\Models\City;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Region;
use App\Models\RegionalIndicator;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Dados de DEMONSTRAÇÃO para visualizar as telas no ambiente local.
 * Recusa rodar fora de APP_ENV=local. Nomes e números são fictícios.
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=LocalDevSeeder
 *   php artisan db:seed --class=DemoSeeder
 *
 * Gera 90 dias de operação: 4 executivos, 6 parceiros, contatos,
 * oportunidades em todas as etapas, visitas, contratos, recebimentos,
 * despesas, indicações e comissões, sempre pelas regras do sistema.
 */
class DemoSeeder extends Seeder
{
    private User $manager;

    /** @var list<Executive> */
    private array $executives = [];

    /** @var array<string, City> */
    private array $cities = [];

    private int $phone = 0;

    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('DemoSeeder só pode ser executado com APP_ENV=local.');
        }

        mt_srand(2026);
        $realNow = CarbonImmutable::now();
        $today = $realNow->startOfDay();
        $this->manager = User::where('email', 'gestor@ricarteprev.test')->firstOrFail();

        SystemSetting::put('routes.cost_per_km', '0.90');
        $this->setupGeography();
        $this->setupTeam();
        [$indicators, $rule] = $this->setupIndicators();

        $sources = LeadSource::query()->pluck('id', 'slug');
        $lossReasons = LossReason::query()->pluck('id')->all();
        $visitReasons = VisitReason::query()->pluck('id')->all();
        $fuel = ExpenseCategory::where('slug', 'combustivel-e-deslocamento')->value('id');
        $food = ExpenseCategory::where('slug', 'alimentacao')->value('id');
        $support = ExpenseCategory::where('slug', 'material-de-apoio')->value('id');

        $first = ['Maria', 'José', 'Antônia', 'Francisco', 'Raimunda', 'João', 'Francisca', 'Antônio', 'Ana', 'Luiz', 'Rita', 'Pedro', 'Luzia', 'Manoel', 'Terezinha', 'Sebastião', 'Cícera', 'Paulo', 'Joana', 'Raimundo', 'Expedita', 'Severino', 'Damiana', 'Cícero'];
        $last = ['Souza', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Araújo', 'Oliveira', 'Batista', 'Bezerra', 'Cavalcante', 'Feitosa', 'Tavares'];
        $interests = ['Aposentadoria por idade', 'Aposentadoria rural', 'BPC/LOAS', 'Pensão por morte', 'Revisão de benefício', 'Salário-maternidade'];
        $bairros = ['Centro', 'Pirajá', 'Salesianos', 'Tiradentes', 'Romeirão', 'Lagoa Seca', 'Muriti', 'Seminário', 'Mirandão', 'Santa Rita'];
        $cityKeys = array_keys($this->cities);

        // ---------- 90 dias de operação ----------
        for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
            $day = $today->subDays($daysAgo);
            if ($day->isSunday()) {
                continue;
            }

            foreach ($this->executives as $i => $exec) {
                $perDay = $day->isSaturday() ? mt_rand(0, 1) : mt_rand(2, 4) + ($i === 0 ? 1 : 0);

                for ($v = 0; $v < $perDay; $v++) {
                    $hour = 8 + $v * 2;
                    $this->at($day->setTime($hour, mt_rand(0, 1) * 30));
                    $city = $this->cities[$cityKeys[($i + $v) % count($cityKeys)]];

                    // Uma parte das visitas é a contatos novos (prospecção), outra a contatos existentes.
                    if (mt_rand(1, 100) <= 55) {
                        $name = $first[mt_rand(0, count($first) - 1)].' '.$last[mt_rand(0, count($last) - 1)];
                        $lead = app(SaveLead::class)->handle(null, [
                            'type' => 'pf', 'name' => $name, 'phone' => $this->nextPhone(), 'city_id' => $city->id,
                            'neighborhood' => $bairros[mt_rand(0, count($bairros) - 1)],
                            'lead_source_id' => $this->pickSource($sources), 'executive_id' => $exec->id,
                            'entered_at' => $day->toDateString(),
                        ], null, null, $exec->user)['lead'];
                    } else {
                        $lead = Lead::query()->where('executive_id', $exec->id)->inRandomOrder()->first();
                    }

                    $visit = app(SaveVisit::class)->handle(null, [
                        'executive_id' => $exec->id, 'lead_id' => $lead?->id, 'contact_name' => $lead ? null : 'Prospecção',
                        'type' => mt_rand(1, 100) <= 75 ? VisitType::FieldVisit : VisitType::Return, 'priority' => 'normal',
                        'scheduled_at' => $day->setTime($hour, 0)->toDateTimeString(), 'city_id' => $city->id,
                        'neighborhood' => $bairros[mt_rand(0, count($bairros) - 1)],
                        'latitude' => (string) ((float) $city->latitude + mt_rand(-150, 150) / 10000),
                        'longitude' => (string) ((float) $city->longitude + mt_rand(-150, 150) / 10000),
                    ], $exec->user);

                    if ($day->setTime($hour, 50)->gte($realNow)) {
                        continue; // visitas de hoje ainda por fazer
                    }

                    $roll = mt_rand(1, 100);
                    if ($roll <= 12) {
                        app(VisitWorkflow::class)->notDone($visit, $visitReasons[mt_rand(0, count($visitReasons) - 1)], null);

                        continue;
                    }
                    if ($roll <= 15 && $daysAgo <= 3) {
                        continue; // algumas sem resultado registrado
                    }

                    $this->at($day->setTime($hour, 50));
                    app(VisitWorkflow::class)->complete($visit, [
                        'started_at' => $day->setTime($hour, 5)->toDateTimeString(),
                        'finished_at' => $day->setTime($hour, 50)->toDateTimeString(),
                        'outcome' => 'Conversa sobre '.mb_strtolower($interests[mt_rand(0, count($interests) - 1)]).'.',
                        'km_reported' => (string) mt_rand(4, 35),
                        'open_opportunity' => $lead !== null && ! $lead->opportunities()->exists() && mt_rand(1, 100) <= 60,
                    ], $exec->user);

                    if (mt_rand(1, 100) <= 45) {
                        $this->expense($exec, $day, mt_rand(1, 100) <= 70 ? $fuel : $food, $city);
                    }
                }
            }

            // Indicações chegando ao longo do período
            if (mt_rand(1, 100) <= 70) {
                $indicator = $indicators[mt_rand(0, count($indicators) - 1)];
                $this->at($day->setTime(10, 0));
                app(RegisterReferral::class)->handle($indicator, [
                    'name' => $first[mt_rand(0, count($first) - 1)].' '.$last[mt_rand(0, count($last) - 1)],
                    'phone' => $this->nextPhone(), 'city_id' => $indicator->city_id,
                    'neighborhood' => $bairros[mt_rand(0, count($bairros) - 1)],
                    'referred_at' => $day->toDateString(), 'consent' => true,
                ], $this->manager);
            }
        }

        // ---------- Evolução das oportunidades pelo funil ----------
        Opportunity::query()->with('lead')->orderBy('id')->each(function (Opportunity $o) use ($today, $lossReasons, $interests) {
            $opened = CarbonImmutable::parse($o->created_at);
            $exec = Executive::with('user')->find($o->executive_id);
            $user = $exec->user;
            $roll = mt_rand(1, 100);
            $o->forceFill(['interest' => $interests[mt_rand(0, count($interests) - 1)], 'estimated_value' => (string) (mt_rand(25, 80) * 100)])->saveQuietly();

            $steps = [OpportunityStage::Qualified, OpportunityStage::Scheduled];
            $date = $opened;
            foreach ($steps as $k => $stage) {
                if ($roll > [78, 55][$k]) {
                    break;
                }
                $date = $date->addDays(mt_rand(2, 6));
                if ($date->gt($today)) {
                    return;
                }
                $this->at($date->setTime(14, 0));
                app(MoveOpportunity::class)->handle($o->fresh(), $stage, $user);
            }

            if ($roll <= 40) {
                $date = $date->addDays(mt_rand(3, 8));
                if ($date->gt($today)) {
                    return;
                }
                $this->at($date->setTime(15, 0));
                app(RegisterProposal::class)->handle($o->fresh(), ['presented_at' => $date->toDateString(), 'amount' => $o->estimated_value], $user);

                if ($roll <= 28) {
                    $date = $date->addDays(mt_rand(2, 10));
                    if ($date->gt($today)) {
                        return;
                    }
                    $this->at($date->setTime(16, 0));
                    $pending = mt_rand(1, 100) <= 12;
                    $contract = app(RegisterContract::class)->handle($o->fresh(), [
                        'status' => $pending ? ContractStatus::Pending : ContractStatus::Signed,
                        'signed_at' => $pending ? null : $date->toDateString(),
                        'contract_value' => (string) $o->estimated_value,
                        'expected_revenue' => (string) round((float) $o->estimated_value * 0.8),
                        'number' => 'RP-'.str_pad((string) $o->id, 4, '0', STR_PAD_LEFT),
                    ], $user);

                    if (! $pending && $date->addDays(20)->lte($today) && mt_rand(1, 100) <= 70) {
                        $this->at($date->addDays(20)->setTime(11, 0));
                        app(RegisterContract::class)->registerReceipt($contract, ['received_at' => $date->addDays(20)->toDateString(), 'amount' => (string) round((float) $contract->expected_revenue * 0.5)], $user);
                    }
                }

                return;
            }

            if ($roll >= 85) {
                $this->at($date->addDays(mt_rand(3, 10))->min($today)->setTime(17, 0));
                app(MoveOpportunity::class)->handle($o->fresh(), OpportunityStage::Lost, $user, 'Sem interesse no momento', $lossReasons[mt_rand(0, count($lossReasons) - 1)]);
            } else {
                $o->forceFill([
                    'next_action' => mt_rand(0, 1) ? 'Ligar para agendar atendimento' : 'Retornar com documentos',
                    'next_action_due' => $today->addDays(mt_rand(-5, 10))->toDateString(),
                ])->saveQuietly();
            }
        });

        $this->at(CarbonImmutable::now());
        $this->expense($this->executives[1], $today->subDays(1), $support, $this->cities['crato'], pending: true);

        // ---------- Aprovação das despesas antigas e comissões ----------
        foreach (Expense::query()->where('status', ExpenseStatus::Pending->value)->where('spent_on', '<', $today->subDays(3))->get() as $expense) {
            app(ReviewExpense::class)->approve($expense, $this->manager);
        }

        foreach (Commission::query()->where('generated_at', '<', $today->subDays(15))->get() as $i => $commission) {
            app(Commissions::class)->approve($commission, (string) $commission->expected_amount, $this->manager);
            if ($i % 2 === 0) {
                app(Commissions::class)->pay($commission->fresh(), (string) $commission->expected_amount, $today->subDays(5)->toDateString(), $this->manager, 'PIX');
            }
        }

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        unset($rule);
    }

    private function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    private function nextPhone(): string
    {
        $this->phone++;

        return '889'.str_pad((string) (90000000 + $this->phone * 37), 8, '0', STR_PAD_LEFT);
    }

    /** @param Collection<string, int> $sources */
    private function pickSource($sources): int
    {
        $r = mt_rand(1, 100);

        return (int) match (true) {
            $r <= 50 => $sources['visitas-externas'],
            $r <= 65 => $sources['instagram'],
            $r <= 78 => $sources['whatsapp'],
            $r <= 88 => $sources['site'],
            default => $sources['parceiros'],
        };
    }

    private function expense(Executive $exec, CarbonImmutable $day, int $category, City $city, bool $pending = false): void
    {
        $expense = app(SaveExpense::class)->handle(null, [
            'description' => $category === ExpenseCategory::where('slug', 'combustivel-e-deslocamento')->value('id') ? 'Abastecimento' : 'Despesa de campo',
            'expense_category_id' => $category,
            'amount' => (string) (mt_rand(30, 160) + mt_rand(0, 99) / 100),
            'spent_on' => $day->toDateString(),
            'executive_id' => $exec->id,
            'city_id' => $city->id,
        ], null, $exec->user);

        if (! $pending && mt_rand(1, 100) <= 4) {
            app(ReviewExpense::class)->reject($expense, $this->manager, 'Sem comprovante');
        }
    }

    private function setupGeography(): void
    {
        $cariri = Region::firstOrCreate(['name' => 'Cariri'], ['description' => 'Juazeiro, Crato, Barbalha e entorno']);
        $centroSul = Region::firstOrCreate(['name' => 'Centro-Sul']);

        foreach (['juazeiro' => [2307304, $cariri], 'crato' => [2304202, $cariri], 'barbalha' => [2301901, $cariri], 'missao-velha' => [2308401, $cariri], 'iguatu' => [2305506, $centroSul]] as $key => [$code, $region]) {
            $city = City::where('ibge_code', $code)->firstOrFail();
            $city->update(['served' => true, 'region_id' => $region->id]);
            $this->cities[$key] = $city;
        }
    }

    private function setupTeam(): void
    {
        $team = [
            ['Darlan da Silva', 'darlan@ricarteprev.test', 'juazeiro', 60, 12],
            ['Jefferson Rocha', 'jefferson@ricarteprev.test', 'crato', 55, 10],
            ['Rogério Souza', 'rogerio@ricarteprev.test', 'barbalha', 55, 10],
            ['Sávio Lima', 'savio@ricarteprev.test', 'iguatu', 50, 8],
        ];

        foreach ($team as [$name, $email, $city, $visits, $contracts]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'active' => true]);
            $user->syncRoles([Role::Executive->value]);
            $this->executives[] = Executive::firstOrCreate(['user_id' => $user->id], [
                'city_id' => $this->cities[$city]->id, 'region_id' => $this->cities[$city]->region_id,
                'hired_at' => now()->subYears(2), 'monthly_visits_target' => $visits, 'monthly_contracts_target' => $contracts,
                'cost_allowance' => '600.00',
            ])->load('user');
        }
    }

    /** @return array{0: list<RegionalIndicator>, 1: CommissionRule} */
    private function setupIndicators(): array
    {
        $rule = CommissionRule::firstOrCreate(['name' => 'Indicação padrão'], ['type' => 'fixed', 'value' => '150.00', 'trigger_event' => 'contract_signed', 'active' => true]);
        $channels = ReferralChannel::query()->pluck('id', 'slug');
        $list = [
            ['João Alves', 'juazeiro', 'moradores-da-regiao', 0],
            ['Maria Souza', 'juazeiro', 'indicacao-de-clientes', 0],
            ['José Ferreira', 'crato', 'moradores-da-regiao', 1],
            ['Ana Paula', 'crato', 'parceiros-locais', 1],
            ['Carlos Mendes', 'barbalha', 'parceiros-locais', 2],
            ['Fernanda Costa', 'missao-velha', 'redes-sociais', 2],
        ];

        $indicators = [];
        foreach ($list as $n => [$name, $city, $channel, $exec]) {
            $indicators[] = RegionalIndicator::firstOrCreate(['whatsapp' => '8898877'.str_pad((string) $n, 4, '0', STR_PAD_LEFT)], [
                'name' => $name, 'city_id' => $this->cities[$city]->id, 'region_id' => $this->cities[$city]->region_id,
                'referral_channel_id' => $channels[$channel], 'commission_rule_id' => $rule->id,
                'default_executive_id' => $this->executives[$exec]->id, 'registered_at' => now()->subMonths(6),
            ]);
        }

        return [$indicators, $rule];
    }
}
