<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use App\Models\Catalogs\LeadSource;
use App\Models\Catalogs\LossReason;
use App\Models\Concerns\ScopedToExecutive;
use Carbon\CarbonImmutable;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $executive_id
 * @property int|null $lead_source_id
 * @property int|null $regional_indicator_id
 * @property int|null $visit_id
 * @property OpportunityStage $stage
 * @property int $furthest_stage_rank
 * @property string|null $interest
 * @property string|null $estimated_value
 * @property string|null $next_action
 * @property CarbonImmutable|null $next_action_due
 * @property int|null $loss_reason_id
 * @property string|null $loss_note
 * @property CarbonImmutable|null $stage_changed_at
 * @property CarbonImmutable|null $last_activity_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Lead $lead
 * @property-read Executive $executive
 * @property-read LeadSource|null $source
 * @property-read RegionalIndicator|null $indicator
 * @property-read LossReason|null $lossReason
 * @property-read Contract|null $contract
 */
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory, ScopedToExecutive, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'lead_id', 'executive_id', 'lead_source_id', 'regional_indicator_id', 'visit_id', 'interest', 'estimated_value',
        'next_action', 'next_action_due', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'furthest_stage_rank' => 'integer',
            'estimated_value' => 'decimal:2',
            'next_action_due' => 'immutable_date',
            'stage_changed_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
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

    /** @return BelongsTo<LossReason, $this> */
    public function lossReason(): BelongsTo
    {
        return $this->belongsTo(LossReason::class);
    }

    /** @return HasMany<OpportunityHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(OpportunityHistory::class)->latest('id');
    }

    /** @return HasMany<Proposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->latest('presented_at');
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->latest('scheduled_at');
    }

    /** @return HasOne<Contract, $this> */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /**
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [OpportunityStage::Signed->value, OpportunityStage::Lost->value, OpportunityStage::Disqualified->value]);
    }

    /**
     * Abertas e sem movimentação há mais dias que o parâmetro funnel.stale_after_days.
     *
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeStale(Builder $query): Builder
    {
        $days = (int) SystemSetting::get('funnel.stale_after_days', 7);

        return $query->open()->where('last_activity_at', '<', now()->subDays($days));
    }

    /**
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('next_action_due', '<', today());
    }

    public function isOverdue(): bool
    {
        return ! $this->stage->isClosed() && $this->next_action_due !== null && $this->next_action_due->lt(today());
    }
}
