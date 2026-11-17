<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Catalogs\LeadSource;
use App\Models\Concerns\ScopedToExecutive;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Contrato. O valor contratado nunca é tratado como dinheiro recebido:
 * o recebido fica em ContractReceipt.
 *
 * @property int $id
 * @property int $opportunity_id
 * @property int $lead_id
 * @property int $executive_id
 * @property int|null $lead_source_id
 * @property int|null $regional_indicator_id
 * @property string|null $number
 * @property ContractStatus $status
 * @property CarbonImmutable|null $signed_at
 * @property string $contract_value
 * @property string|null $expected_revenue
 * @property string|null $notes
 * @property-read Lead $lead
 * @property-read Executive $executive
 * @property-read Opportunity $opportunity
 * @property-read LeadSource|null $source
 * @property-read RegionalIndicator|null $indicator
 * @property-read Collection<int, C2Process> $c2Processes
 */
class Contract extends Model
{
    use LogsActivity, ScopedToExecutive, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'opportunity_id', 'lead_id', 'executive_id', 'lead_source_id', 'regional_indicator_id', 'number',
        'status', 'signed_at', 'contract_value', 'expected_revenue', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'signed_at' => 'immutable_date',
            'contract_value' => 'decimal:2',
            'expected_revenue' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    /** @return BelongsTo<Executive, $this> */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class)->withTrashed();
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /** @return BelongsTo<RegionalIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RegionalIndicator::class, 'regional_indicator_id');
    }

    /** @return HasMany<ContractReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(ContractReceipt::class)->latest('received_at');
    }

    /**
     * Processos do cliente no C2 Prev (ligados ao contato pelo CPF).
     *
     * @return HasMany<C2Process, $this>
     */
    public function c2Processes(): HasMany
    {
        return $this->hasMany(C2Process::class, 'lead_id', 'lead_id');
    }

    /**
     * Assinados há mais de $days dias e ainda sem processo no C2 com o CPF do cliente.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMissingInC2(Builder $query, int $days): void
    {
        $query->where('contracts.status', ContractStatus::Signed->value)
            ->where('contracts.signed_at', '<=', now()->subDays($days)->toDateString())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('c2_processes')->whereColumn('c2_processes.lead_id', 'contracts.lead_id'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('contratos')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
