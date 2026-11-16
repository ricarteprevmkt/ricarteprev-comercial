<?php

namespace App\Models;

use App\Enums\CommissionBase;
use App\Enums\CommissionTrigger;
use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Regra de comissão de parceiro. Só gera comissão quando associada a um
 * parceiro e quando o evento configurado acontece (Fase 5).
 *
 * @property int $id
 * @property string $name
 * @property CommissionType $type
 * @property string $value
 * @property CommissionBase|null $base
 * @property CommissionTrigger $trigger_event
 * @property string|null $description
 * @property bool $active
 */
class CommissionRule extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'type', 'value', 'base', 'trigger_event', 'description', 'active'];

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return [
            'type' => CommissionType::class,
            'base' => CommissionBase::class,
            'trigger_event' => CommissionTrigger::class,
            'value' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<RegionalIndicator, $this> */
    public function indicators(): HasMany
    {
        return $this->hasMany(RegionalIndicator::class);
    }

    /**
     * @param  Builder<CommissionRule>  $query
     * @return Builder<CommissionRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function summary(): string
    {
        $value = $this->type === CommissionType::Fixed
            ? 'R$ '.number_format((float) $this->value, 2, ',', '.')
            : number_format((float) $this->value, 2, ',', '.').'% sobre '.mb_strtolower($this->base?->label() ?? '');

        return "{$value}, {$this->trigger_event->label()}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('comissoes')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
