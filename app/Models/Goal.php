<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Meta mensal de um executivo.
 *
 * @property int $id
 * @property int $executive_id
 * @property CarbonImmutable $month
 * @property int $visits_target
 * @property int $contracts_target
 * @property int|null $created_by
 */
class Goal extends Model
{
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['executive_id', 'month', 'visits_target', 'contracts_target', 'created_by'];

    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'visits_target' => 'integer',
            'contracts_target' => 'integer',
        ];
    }

    /** @return BelongsTo<Executive, $this> */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('metas')->logOnly(['executive_id', 'month', 'visits_target', 'contracts_target'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
