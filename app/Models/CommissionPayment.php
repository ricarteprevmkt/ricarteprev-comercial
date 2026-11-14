<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $commission_id
 * @property CarbonImmutable $paid_at
 * @property string $amount
 * @property string|null $notes
 * @property int|null $created_by
 */
class CommissionPayment extends Model
{
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['commission_id', 'paid_at', 'amount', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['paid_at' => 'immutable_date', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Commission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('comissoes')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
