<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use App\Models\Catalogs\ReferralChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $regional_indicator_id
 * @property int $lead_id
 * @property int|null $executive_id
 * @property int|null $referral_channel_id
 * @property CarbonImmutable $referred_at
 * @property ReferralStatus $status
 * @property int $furthest_rank
 * @property bool $is_duplicate
 * @property int|null $duplicate_of_referral_id
 * @property bool $consent_confirmed
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read RegionalIndicator $indicator
 * @property-read Lead $lead
 * @property-read Executive|null $executive
 * @property-read ReferralChannel|null $channel
 */
class Referral extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'regional_indicator_id', 'lead_id', 'executive_id', 'referral_channel_id', 'referred_at',
        'is_duplicate', 'duplicate_of_referral_id', 'consent_confirmed', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'referred_at' => 'immutable_date',
            'status' => ReferralStatus::class,
            'furthest_rank' => 'integer',
            'is_duplicate' => 'boolean',
            'consent_confirmed' => 'boolean',
        ];
    }

    /** @return BelongsTo<RegionalIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RegionalIndicator::class, 'regional_indicator_id')->withTrashed();
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

    /** @return BelongsTo<ReferralChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(ReferralChannel::class, 'referral_channel_id');
    }

    /** @return BelongsTo<Referral, $this> */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'duplicate_of_referral_id');
    }

    /** @return HasMany<ReferralHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(ReferralHistory::class)->latest('id');
    }

    /**
     * Quem tem "referrals.view_all" vê todas; o parceiro vê as próprias;
     * o executivo vê as que estão com ele.
     *
     * @param  Builder<Referral>  $query
     * @return Builder<Referral>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('referrals.view_all')) {
            return $query;
        }

        $indicatorId = $user->regionalIndicator()->value('id');
        $executiveId = $user->executive()->value('id');

        return $query->where(fn ($q) => $q
            ->where('referrals.regional_indicator_id', $indicatorId ?? 0)
            ->orWhere('referrals.executive_id', $executiveId ?? 0));
    }
}
