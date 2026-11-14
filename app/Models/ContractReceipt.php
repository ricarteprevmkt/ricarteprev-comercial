<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Valor efetivamente recebido de um contrato.
 *
 * @property int $id
 * @property int $contract_id
 * @property CarbonImmutable $received_at
 * @property string $amount
 * @property string|null $notes
 */
class ContractReceipt extends Model
{
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['contract_id', 'received_at', 'amount', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_date', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('contratos')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
