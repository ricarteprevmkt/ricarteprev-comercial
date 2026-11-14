<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Comissão de indicador gerada por um evento (contrato assinado ou
 * recebimento), conforme a regra vigente no momento da geração.
 *
 * @property int $id
 * @property int $regional_indicator_id
 * @property int|null $referral_id
 * @property int $contract_id
 * @property int|null $contract_receipt_id
 * @property int|null $commission_rule_id
 * @property string $rule_snapshot
 * @property string|null $base_amount
 * @property string $expected_amount
 * @property string|null $approved_amount
 * @property CommissionStatus $status
 * @property CarbonImmutable $generated_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $notes
 * @property-read RegionalIndicator $indicator
 * @property-read Contract $contract
 */
class Commission extends Model
{
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'regional_indicator_id', 'referral_id', 'contract_id', 'contract_receipt_id', 'commission_rule_id',
        'rule_snapshot', 'base_amount', 'expected_amount', 'generated_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'expected'];

    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'base_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'generated_at' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<RegionalIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RegionalIndicator::class, 'regional_indicator_id')->withTrashed();
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    /** @return BelongsTo<Referral, $this> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /** @return HasMany<CommissionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CommissionPayment::class)->latest('paid_at');
    }

    public function paidTotal(): string
    {
        return number_format((float) $this->payments()->sum('amount'), 2, '.', '');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('comissoes')
            ->logOnly(['status', 'expected_amount', 'approved_amount', 'approved_by', 'notes'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
