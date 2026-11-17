<?php

namespace App\Actions\C2;

use App\Enums\ContractStatus;
use App\Enums\InssOutcome;
use App\Models\C2Process;
use App\Models\Contract;
use App\Models\SystemSetting;
use Carbon\CarbonImmutable;

/**
 * Separa os protocolos do escritório dos protocolos antigos que o C2 traz da
 * base do INSS. Os de fora não entram no administrativo nem no resultado do
 * comercial (continuam guardados e aparecem na aba "Fora do escopo").
 *
 * - Cliente com contrato assinado: conta o protocolo aberto a partir da
 *   primeira assinatura, com uma folga de c2.scope_tolerance_days dias antes.
 * - Cliente sem contrato no sistema: conta o protocolo em andamento ou aberto
 *   nos últimos c2.scope_months meses.
 * - Protocolo sem data de abertura: conta (não dá para saber).
 */
final class RefreshC2Scope
{
    /** @return int quantos processos mudaram */
    public function handle(): int
    {
        $tolerance = max(0, (int) SystemSetting::get('c2.scope_tolerance_days', 30));
        $months = max(1, (int) SystemSetting::get('c2.scope_months', 12));
        $recent = today()->subMonths($months)->toDateString();

        /** @var array<int, string> $firstSigned lead_id => primeira assinatura */
        $firstSigned = Contract::query()
            ->where('status', ContractStatus::Signed->value)->whereNotNull('signed_at')
            ->selectRaw('lead_id, min(signed_at) as first_signed')->groupBy('lead_id')
            ->pluck('first_signed', 'lead_id')->all();

        $changed = 0;

        C2Process::query()->select(['id', 'lead_id', 'opened_at', 'outcome', 'in_scope'])->chunkById(500, function ($processes) use ($firstSigned, $tolerance, $recent, &$changed) {
            foreach ($processes as $process) {
                $opened = $process->opened_at?->toDateString();
                $signed = $process->lead_id !== null ? ($firstSigned[$process->lead_id] ?? null) : null;

                $inScope = match (true) {
                    $opened === null => true,
                    $signed !== null => $opened >= CarbonImmutable::parse($signed)->subDays($tolerance)->toDateString(),
                    default => $process->outcome === InssOutcome::InProgress || $opened >= $recent,
                };

                if ($inScope !== $process->in_scope) {
                    C2Process::query()->whereKey($process->id)->update(['in_scope' => $inScope]);
                    $changed++;
                }
            }
        });

        return $changed;
    }
}
