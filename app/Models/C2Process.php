<?php

namespace App\Models;

use App\Enums\InssOutcome;
use Carbon\CarbonImmutable;
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
 * @property string|null $request_number
 * @property string|null $benefit_type
 * @property string|null $status_text
 * @property string|null $decision_text
 * @property InssOutcome $outcome
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $concluded_at
 * @property CarbonImmutable $synced_at
 * @property-read Lead|null $lead
 */
class C2Process extends Model
{
    protected $table = 'c2_processes';

    /** @var list<string> */
    protected $fillable = [
        'c2_id', 'c2_client_id', 'client_name', 'cpf_hash', 'cpf_last_digits', 'lead_id', 'request_number',
        'benefit_type', 'status_text', 'decision_text', 'outcome', 'opened_at', 'concluded_at', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => InssOutcome::class,
            'opened_at' => 'immutable_date',
            'concluded_at' => 'immutable_date',
            'synced_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
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
