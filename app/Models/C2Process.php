<?php

namespace App\Models;

use App\Enums\InssOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Processo do Meu INSS lido do C2 Prev. O CPF não é guardado: só o hash
 * (para ligar ao contato) e os dois últimos dígitos (para conferência).
 *
 * @property int $id
 * @property string $c2_id
 * @property string $c2_client_id
 * @property string|null $client_name
 * @property string|null $cpf_hash
 * @property string|null $cpf_last_digits
 * @property int|null $lead_id
 * @property bool $in_scope
 * @property string|null $request_number
 * @property string|null $benefit_number
 * @property string|null $benefit_type
 * @property string|null $service
 * @property string|null $status_text
 * @property string|null $decision_text
 * @property string|null $requirement_text
 * @property CarbonImmutable|null $requirement_deadline
 * @property CarbonImmutable|null $appointment_date
 * @property string|null $appointment_time
 * @property string|null $appointment_location
 * @property string|null $decision_reason
 * @property CarbonImmutable|null $appeal_deadline
 * @property string|null $monitoring_state
 * @property string|null $monitoring_action
 * @property InssOutcome $outcome
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $concluded_at
 * @property CarbonImmutable|null $checked_at
 * @property CarbonImmutable $synced_at
 * @property-read Lead|null $lead
 */
class C2Process extends Model
{
    protected $table = 'c2_processes';

    /** @var list<string> */
    protected $fillable = [
        'c2_id', 'c2_client_id', 'client_name', 'cpf_hash', 'cpf_last_digits', 'lead_id', 'in_scope', 'request_number',
        'benefit_number', 'benefit_type', 'service', 'status_text', 'decision_text', 'requirement_text', 'requirement_deadline',
        'appointment_date', 'appointment_time', 'appointment_location', 'decision_reason', 'appeal_deadline',
        'monitoring_state', 'monitoring_action', 'outcome', 'opened_at', 'concluded_at', 'checked_at', 'synced_at',
    ];

    /** Situações do monitoramento no C2 que pedem ação do escritório. */
    public const MONITORING_PROBLEMS = ['action_required', 'degraded', 'paused'];

    protected function casts(): array
    {
        return [
            'outcome' => InssOutcome::class,
            'opened_at' => 'immutable_date',
            'concluded_at' => 'immutable_date',
            'requirement_deadline' => 'immutable_date',
            'appointment_date' => 'immutable_date',
            'appeal_deadline' => 'immutable_date',
            'checked_at' => 'immutable_datetime',
            'synced_at' => 'immutable_datetime',
            'in_scope' => 'boolean',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    /**
     * Só os protocolos do escritório (fora os antigos, veja RefreshC2Scope).
     *
     * @param  Builder<self>  $query
     */
    public function scopeInScope(Builder $query): void
    {
        $query->where('c2_processes.in_scope', true);
    }

    /**
     * Exigência aberta: processo em andamento com exigência informada pelo INSS.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpenRequirement(Builder $query): void
    {
        $query->where('outcome', InssOutcome::InProgress->value)
            ->where(fn ($q) => $q->whereNotNull('requirement_deadline')->orWhereNotNull('requirement_text'));
    }

    /**
     * Indeferido com prazo de recurso a partir de $from.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAppealOpen(Builder $query, ?string $from = null): void
    {
        $query->where('outcome', InssOutcome::Denied->value)
            ->where('appeal_deadline', '>=', $from ?? today()->toDateString());
    }

    /**
     * O C2 parou de acompanhar (senha do gov.br inválida, autenticação pendente).
     *
     * @param  Builder<self>  $query
     */
    public function scopeMonitoringProblem(Builder $query): void
    {
        $query->whereIn('monitoring_state', self::MONITORING_PROBLEMS);
    }

    /** Ficha do cliente na plataforma do C2. */
    public function c2Url(): ?string
    {
        $base = rtrim((string) config('services.c2.app_url'), '/');

        return $base !== '' && $this->c2_client_id !== '' ? $base.'/clientes/'.rawurlencode($this->c2_client_id) : null;
    }

    public function monitoringLabel(): ?string
    {
        return match (true) {
            $this->monitoring_action === 'password_invalid' => 'Senha do gov.br inválida',
            $this->monitoring_action === 'authentication_required' => 'Autenticação pendente no gov.br',
            $this->monitoring_state === 'action_required' => 'Ação necessária no C2',
            $this->monitoring_state === 'degraded' => 'Leitura com falhas no C2',
            $this->monitoring_state === 'paused' => 'Acompanhamento pausado no C2',
            default => null,
        };
    }

    /**
     * Resultado de um cliente com vários processos: deferido vence, depois em
     * andamento, depois indeferido.
     *
     * @param  iterable<self>  $processes
     */
    public static function overallOutcome(iterable $processes): ?InssOutcome
    {
        $outcomes = collect($processes)->map(fn (self $p) => $p->outcome);

        foreach ([InssOutcome::Granted, InssOutcome::InProgress, InssOutcome::Denied, InssOutcome::Concluded] as $candidate) {
            if ($outcomes->contains($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
