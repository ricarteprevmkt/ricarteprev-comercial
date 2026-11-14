<?php

namespace App\Metrics;

use App\Enums\CommissionStatus;
use App\Enums\ContractStatus;
use App\Models\Catalogs\ReferralChannel;
use App\Models\City;
use App\Models\Commission;
use App\Models\CommissionPayment;
use App\Models\Contract;
use App\Models\Referral;
use App\Models\RegionalIndicator;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;

/**
 * Métricas de indicações regionais (separadas da produtividade dos executivos).
 *
 * Base (coorte): indicações válidas (não duplicadas) com data no período.
 * "Chegou a X": furthest_rank >= rank(X). Contratos originados: contratos
 * assinados no período com indicador atribuído.
 */
final class ReferralMetrics
{
    public function __construct(
        private Period $period,
        private ?int $regionId = null,
        private ?int $indicatorId = null,
    ) {}

    /**
     * @return array{total: int, contacted: int, qualified: int, opportunities: int, proposals: int, signed: int, lost: int, duplicates: int, conversion: float|null}
     */
    public function totals(): array
    {
        $r = $this->cohort()
            ->selectRaw('count(*) as total, sum(furthest_rank >= 2) as r2, sum(furthest_rank >= 3) as r3, sum(furthest_rank >= 4) as r4, sum(furthest_rank >= 5) as r5, sum(furthest_rank >= 6) as r6')
            ->selectRaw("sum(referrals.status = 'lost') as lost")
            ->toBase()->first();

        $total = (int) ($r->total ?? 0);

        return [
            'total' => $total,
            'contacted' => (int) ($r->r2 ?? 0),
            'qualified' => (int) ($r->r3 ?? 0),
            'opportunities' => (int) ($r->r4 ?? 0),
            'proposals' => (int) ($r->r5 ?? 0),
            'signed' => (int) ($r->r6 ?? 0),
            'lost' => (int) ($r->lost ?? 0),
            'duplicates' => $this->filtered(Referral::query())->where('is_duplicate', true)->whereBetween('referred_at', $this->dates())->count(),
            'conversion' => FunnelMetrics::pct((int) ($r->r6 ?? 0), $total),
        ];
    }

    public function contractsOriginated(): int
    {
        return $this->filteredContracts()->count();
    }

    public function revenueOriginated(): string
    {
        return number_format((float) $this->filteredContracts()->sum('contract_value'), 2, '.', '');
    }

    /** @return array{expected: string, approved: string, paid: string} */
    public function commissions(): array
    {
        $base = fn () => $this->filteredByIndicator(Commission::query());

        return [
            'expected' => number_format((float) $base()->where('status', CommissionStatus::Expected->value)->whereBetween('generated_at', $this->dates())->sum('expected_amount'), 2, '.', ''),
            'approved' => number_format((float) $base()->whereIn('status', [CommissionStatus::Approved->value, CommissionStatus::Paid->value])->whereBetween('approved_at', $this->period->range())->sum('approved_amount'), 2, '.', ''),
            'paid' => number_format((float) $this->filteredByIndicator(CommissionPayment::query()->join('commissions', 'commissions.id', '=', 'commission_payments.commission_id'))
                ->whereBetween('commission_payments.paid_at', $this->dates())->sum('commission_payments.amount'), 2, '.', ''),
        ];
    }

    /**
     * @return list<array{city: City, total: int, qualified: int, opportunities: int, signed: int, conversion: float|null}>
     */
    public function byCity(): array
    {
        $rows = $this->cohort()
            ->join('leads', 'leads.id', '=', 'referrals.lead_id')
            ->whereNotNull('leads.city_id')
            ->select('leads.city_id')
            ->selectRaw('count(*) as total, sum(furthest_rank >= 3) as r3, sum(furthest_rank >= 4) as r4, sum(furthest_rank >= 6) as r6')
            ->groupBy('leads.city_id')
            ->toBase()->get();

        /** @var array<int, City> $cities */
        $cities = City::query()->whereKey($rows->pluck('city_id'))->get()->keyBy('id')->all();

        return array_values($rows->map(fn ($r): array => [
            'city' => $cities[(int) $r->city_id],
            'total' => (int) $r->total,
            'qualified' => (int) $r->r3,
            'opportunities' => (int) $r->r4,
            'signed' => (int) $r->r6,
            'conversion' => FunnelMetrics::pct((int) $r->r6, (int) $r->total),
        ])->sortByDesc('total')->all());
    }

    /**
     * @return list<array{indicator: RegionalIndicator, total: int, qualified: int, opportunities: int, signed: int, conversion: float|null}>
     */
    public function byIndicator(int $limit = 10): array
    {
        $rows = $this->cohort()
            ->select('referrals.regional_indicator_id')
            ->selectRaw('count(*) as total, sum(furthest_rank >= 3) as r3, sum(furthest_rank >= 4) as r4, sum(furthest_rank >= 6) as r6')
            ->groupBy('referrals.regional_indicator_id')
            ->orderByDesc('total')->orderByDesc('r6')
            ->limit($limit)
            ->toBase()->get();

        /** @var array<int, RegionalIndicator> $indicators */
        $indicators = RegionalIndicator::withTrashed()->with('city')->whereKey($rows->pluck('regional_indicator_id'))->get()->keyBy('id')->all();

        return array_values($rows->map(fn ($r): array => [
            'indicator' => $indicators[(int) $r->regional_indicator_id],
            'total' => (int) $r->total,
            'qualified' => (int) $r->r3,
            'opportunities' => (int) $r->r4,
            'signed' => (int) $r->r6,
            'conversion' => FunnelMetrics::pct((int) $r->r6, (int) $r->total),
        ])->all());
    }

    /**
     * @return list<array{channel: string, total: int, share: float|null}>
     */
    public function byChannel(): array
    {
        $rows = $this->cohort()->select('referrals.referral_channel_id')->selectRaw('count(*) as total')
            ->groupBy('referrals.referral_channel_id')->toBase()->get();
        $sum = (int) $rows->sum('total');

        $names = [];
        foreach (ReferralChannel::query()->get(['id', 'name']) as $channel) {
            $names[$channel->id] = $channel->name;
        }

        return array_values($rows->map(fn ($r): array => [
            'channel' => $r->referral_channel_id ? ($names[(int) $r->referral_channel_id] ?? 'Canal removido') : 'Não informado',
            'total' => (int) $r->total,
            'share' => FunnelMetrics::pct((int) $r->total, $sum),
        ])->sortByDesc('total')->all());
    }

    /**
     * Pontos para o mapa: municípios com indicações no período.
     *
     * @return list<array{lat: float, lng: float, name: string, total: int, signed: int}>
     */
    public function mapPoints(): array
    {
        $points = [];

        foreach ($this->byCity() as $row) {
            if ($row['city']->latitude === null) {
                continue;
            }
            $points[] = [
                'lat' => (float) $row['city']->latitude,
                'lng' => (float) $row['city']->longitude,
                'name' => $row['city']->label(),
                'total' => $row['total'],
                'signed' => $row['signed'],
            ];
        }

        return $points;
    }

    public function previous(): self
    {
        return new self($this->period->previous(), $this->regionId, $this->indicatorId);
    }

    /** @return Builder<Referral> */
    private function cohort(): Builder
    {
        return $this->filtered(Referral::query())
            ->where('referrals.is_duplicate', false)
            ->whereBetween('referrals.referred_at', $this->dates());
    }

    /**
     * @param  Builder<Referral>  $query
     * @return Builder<Referral>
     */
    private function filtered(Builder $query): Builder
    {
        return $query
            ->when($this->indicatorId, fn ($q) => $q->where('referrals.regional_indicator_id', $this->indicatorId))
            ->when($this->regionId, fn ($q) => $q->whereIn('referrals.regional_indicator_id', RegionalIndicator::withTrashed()->where('region_id', $this->regionId)->select('id')));
    }

    /** @return Builder<Contract> */
    private function filteredContracts(): Builder
    {
        return $this->filteredByIndicator(Contract::query())
            ->whereNotNull('contracts.regional_indicator_id')
            ->where('contracts.status', ContractStatus::Signed->value)
            ->whereBetween('contracts.signed_at', $this->dates());
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function filteredByIndicator(Builder $query): Builder
    {
        $column = $query->getModel() instanceof CommissionPayment ? 'commissions.regional_indicator_id' : $query->getModel()->qualifyColumn('regional_indicator_id');

        return $query
            ->when($this->indicatorId, fn ($q) => $q->where($column, $this->indicatorId))
            ->when($this->regionId, fn ($q) => $q->whereIn($column, RegionalIndicator::withTrashed()->where('region_id', $this->regionId)->select('id')));
    }

    /** @return array{0: string, 1: string} */
    private function dates(): array
    {
        return [$this->period->start->toDateString(), $this->period->end->toDateString()];
    }
}
