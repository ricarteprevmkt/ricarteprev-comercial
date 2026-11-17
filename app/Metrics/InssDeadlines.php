<?php

namespace App\Metrics;

use App\Models\C2Process;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Agenda única dos prazos dos processos no INSS: exigências a cumprir,
 * agendamentos (perícia, avaliação) e prazos de recurso dos indeferidos.
 * Só protocolos do escritório.
 */
final class InssDeadlines
{
    public const TYPES = [
        'exigencia' => 'Exigência',
        'agendamento' => 'Agendamento',
        'recurso' => 'Prazo de recurso',
    ];

    /**
     * @param  int|null  $days  até quantos dias à frente (null = todos)
     * @param  string|null  $type  uma das chaves de TYPES
     * @param  bool  $overdue  inclui exigências vencidas
     * @return Collection<int, array{type: string, label: string, date: CarbonImmutable, days: int, detail: string|null, process: C2Process}>
     */
    public function list(?int $days = 30, ?string $type = null, bool $overdue = true): Collection
    {
        $today = CarbonImmutable::today();
        $until = $days === null ? null : $today->addDays($days)->toDateString();
        $items = collect();

        if ($type === null || $type === 'exigencia') {
            C2Process::query()->inScope()->openRequirement()->whereNotNull('requirement_deadline')
                ->when(! $overdue, fn ($q) => $q->where('requirement_deadline', '>=', $today->toDateString()))
                ->when($until, fn ($q) => $q->where('requirement_deadline', '<=', $until))
                ->get()
                ->each(fn (C2Process $p) => $items->push($this->item('exigencia', $p->requirement_deadline, $p->requirement_text, $p, $today)));
        }

        if ($type === null || $type === 'agendamento') {
            C2Process::query()->inScope()->whereNotNull('appointment_date')
                ->where('appointment_date', '>=', $today->toDateString())
                ->when($until, fn ($q) => $q->where('appointment_date', '<=', $until))
                ->get()
                ->each(fn (C2Process $p) => $items->push($this->item('agendamento', $p->appointment_date,
                    trim(($p->appointment_time ? $p->appointment_time.' · ' : '').($p->appointment_location ?? ''), ' ·') ?: null, $p, $today)));
        }

        if ($type === null || $type === 'recurso') {
            C2Process::query()->inScope()->appealOpen()
                ->when($until, fn ($q) => $q->where('appeal_deadline', '<=', $until))
                ->get()
                ->each(fn (C2Process $p) => $items->push($this->item('recurso', $p->appeal_deadline, $p->decision_reason, $p, $today)));
        }

        return $items->sortBy(fn (array $i) => $i['date']->timestamp)->values();
    }

    /** @return array{type: string, label: string, date: CarbonImmutable, days: int, detail: string|null, process: C2Process} */
    private function item(string $type, ?CarbonImmutable $date, ?string $detail, C2Process $process, CarbonImmutable $today): array
    {
        $date ??= $today;

        return [
            'type' => $type,
            'label' => self::TYPES[$type],
            'date' => $date,
            'days' => (int) $today->diffInDays($date, false),
            'detail' => $detail,
            'process' => $process,
        ];
    }

    /** Cor do prazo: vencido, até 3 dias, até 7 dias, mais longe. */
    public static function color(int $days): string
    {
        return match (true) {
            $days < 0 => 'red',
            $days <= 3 => 'orange',
            $days <= 7 => 'amber',
            default => 'zinc',
        };
    }

    public static function when(int $days): string
    {
        return match (true) {
            $days < -1 => 'venceu há '.abs($days).' dias',
            $days === -1 => 'venceu ontem',
            $days === 0 => 'hoje',
            $days === 1 => 'amanhã',
            default => "em {$days} dias",
        };
    }
}
