<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Totais do escritório lidos do C2 (painel). Só números agregados.
 *
 * @property int $id
 * @property array<string, float|null> $metrics
 * @property CarbonImmutable $collected_at
 */
class C2Snapshot extends Model
{
    public const UPDATED_AT = null;

    /** Guardado por este tempo; o mais antigo é apagado ao receber um novo. */
    public const KEEP_DAYS = 120;

    /** Indicadores aceitos, por grupo. Qualquer outra chave é recusada. */
    public const METRICS = [
        'financeiro' => [
            'received_month' => 'Recebido no mês',
            'received_prev_month' => 'Recebido no mês anterior',
            'receivable_open' => 'A receber em aberto',
            'receivable_overdue' => 'A receber vencido',
            'payable_month' => 'A pagar no mês',
            'paid_month' => 'Pago no mês',
            'payable_overdue' => 'A pagar vencido',
        ],
        'prazos' => [
            'deadlines_overdue' => 'Prazos atrasados',
            'deadlines_next7' => 'Prazos nos próximos 7 dias',
            'tasks_overdue' => 'Tarefas atrasadas',
        ],
        'gestantes' => [
            'pregnant_total' => 'Processos de gestante',
            'pregnant_entry_open' => 'Entrada liberada, sem requerimento',
            'pregnant_entry_next30' => 'Entrada abre nos próximos 30 dias',
            'pregnant_dpp_next30' => 'DPP nos próximos 30 dias',
            'guides_pending' => 'Contribuição pendente',
            'guides_overdue' => 'Guia com data vencida',
            'guides_next30' => 'Guia com data nos próximos 30 dias',
            'guides_paid_month' => 'Guias pagas nos últimos 30 dias',
        ],
        'judicial' => [
            'judicial_active' => 'Processos judiciais ativos',
            'judicial_new_month' => 'Judiciais novos no mês',
            'judicial_stale_90d' => 'Sem movimentação há 90 dias',
        ],
        'crm' => [
            'clients_new_month' => 'Clientes novos no mês',
            'clients_new_prev_month' => 'Clientes novos no mês anterior',
            'leads_month' => 'Leads do CRM no mês',
            'leads_converted_month' => 'Leads convertidos no mês',
        ],
    ];

    /** @var list<string> */
    protected $fillable = ['metrics', 'collected_at'];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'collected_at' => 'immutable_datetime'];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(array_merge(...array_values(self::METRICS)));
    }

    public static function latest(): ?self
    {
        return static::query()->latest('collected_at')->latest('id')->first();
    }

    public function value(string $key): ?float
    {
        $v = $this->metrics[$key] ?? null;

        return $v === null ? null : (float) $v;
    }

    /** Desatualizado: a tarefa deveria mandar a cada 30 minutos. */
    public function isStale(): bool
    {
        return $this->collected_at->lt(now()->subHours(3));
    }
}
