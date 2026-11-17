<?php

namespace App\Metrics;

use App\Enums\ContractStatus;
use App\Enums\InssOutcome;
use App\Models\C2Process;
use App\Models\Contract;
use App\Support\Period;
use Illuminate\Support\Collection;

/**
 * Resultado no INSS dos contratos assinados no período, a partir dos
 * processos lidos do C2 Prev. Só indicador: não altera contrato nem comissão.
 *
 * Taxa de deferimento = deferidos ÷ (deferidos + indeferidos). Contratos em
 * andamento ou sem processo no C2 ficam fora da taxa.
 */
final class InssMetrics
{
    public const GROUPS = ['executivo' => 'Executivo', 'origem' => 'Origem', 'parceiro' => 'Parceiro'];

    /** @param  list<int>|null  $executiveIds */
    public function __construct(private Period $period, private ?array $executiveIds = null) {}

    /** @return array{signed: int, missing: int, in_progress: int, granted: int, denied: int, rate: float|null} */
    public function totals(): array
    {
        return $this->summarize($this->contracts());
    }

    /**
     * @return list<array{label: string, signed: int, missing: int, in_progress: int, granted: int, denied: int, rate: float|null}>
     */
    public function by(string $group): array
    {
        $key = match ($group) {
            'origem' => fn (Contract $c) => $c->source->name ?? 'Sem origem',
            'parceiro' => fn (Contract $c) => $c->indicator->name ?? 'Sem parceiro',
            default => fn (Contract $c) => $c->executive->user->name,
        };

        return array_values($this->contracts()
            ->groupBy($key)
            ->map(fn (Collection $items, string $label) => ['label' => $label] + $this->summarize($items))
            ->sortByDesc('signed')
            ->all());
    }

    /**
     * @param  Collection<int, Contract>  $contracts
     * @return array{signed: int, missing: int, in_progress: int, granted: int, denied: int, rate: float|null}
     */
    private function summarize(Collection $contracts): array
    {
        $outcomes = $contracts->map(fn (Contract $c) => C2Process::overallOutcome($c->c2Processes));
        $granted = $outcomes->filter(fn ($o) => $o === InssOutcome::Granted)->count();
        $denied = $outcomes->filter(fn ($o) => $o === InssOutcome::Denied)->count();

        return [
            'signed' => $contracts->count(),
            'missing' => $outcomes->filter(fn ($o) => $o === null)->count(),
            'in_progress' => $outcomes->filter(fn ($o) => $o === InssOutcome::InProgress || $o === InssOutcome::Concluded)->count(),
            'granted' => $granted,
            'denied' => $denied,
            'rate' => FunnelMetrics::pct($granted, $granted + $denied),
        ];
    }

    /** @return Collection<int, Contract> */
    private function contracts(): Collection
    {
        return Contract::query()
            ->with(['c2Processes', 'executive.user', 'source', 'indicator'])
            ->where('status', ContractStatus::Signed->value)
            ->whereBetween('signed_at', [$this->period->start->toDateString(), $this->period->end->toDateString()])
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('executive_id', $this->executiveIds ?: [0]))
            ->get();
    }
}
