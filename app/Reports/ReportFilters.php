<?php

namespace App\Reports;

use App\Models\Executive;
use App\Models\User;
use App\Support\Period;
use App\Support\Visibility;

/**
 * Filtros comuns dos relatórios. O recorte por permissão é aplicado aqui:
 * quem não vê a equipe inteira fica restrito ao próprio executivo.
 */
final class ReportFilters
{
    public function __construct(
        public Period $period,
        public ?int $executiveId = null,
        public ?int $indicatorId = null,
        public ?int $cityId = null,
        public ?int $regionId = null,
        public ?int $sourceId = null,
        public ?string $stage = null,
        /** @var list<int>|null null = sem restrição de perfil */
        public ?array $allowedExecutives = null,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input, User $user): self
    {
        $int = fn (string $k): ?int => isset($input[$k]) && $input[$k] !== '' && is_numeric($input[$k]) ? (int) $input[$k] : null;

        $period = ! empty($input['start']) && ! empty($input['end'])
            ? Period::custom((string) $input['start'], (string) $input['end'])
            : Period::current();

        return new self(
            period: $period,
            executiveId: $int('executive'),
            indicatorId: $int('indicator'),
            cityId: $int('city'),
            regionId: $int('region'),
            sourceId: $int('source'),
            stage: isset($input['stage']) && $input['stage'] !== '' ? (string) $input['stage'] : null,
            allowedExecutives: Visibility::executiveIds($user),
        );
    }

    /**
     * Executivos considerados: combinação do filtro escolhido, da região e da permissão.
     *
     * @return list<int>|null null = todos
     */
    public function executiveIds(): ?array
    {
        $ids = $this->allowedExecutives;

        if ($this->executiveId !== null) {
            $ids = $ids === null ? [$this->executiveId] : array_values(array_intersect($ids, [$this->executiveId]));
        }

        if ($this->regionId !== null) {
            $inRegion = Executive::withTrashed()->where('region_id', $this->regionId)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $ids = $ids === null ? $inRegion : array_values(array_intersect($ids, $inRegion));
        }

        return $ids === null ? null : array_values($ids);
    }

    /** @return array{0: string, 1: string} */
    public function dates(): array
    {
        return [$this->period->start->toDateString(), $this->period->end->toDateString()];
    }

    /** @return array<string, string|int|null> */
    public function toQuery(): array
    {
        return array_filter([
            'start' => $this->period->start->toDateString(),
            'end' => $this->period->end->toDateString(),
            'executive' => $this->executiveId,
            'indicator' => $this->indicatorId,
            'city' => $this->cityId,
            'region' => $this->regionId,
            'source' => $this->sourceId,
            'stage' => $this->stage,
        ], fn ($v) => $v !== null);
    }
}
