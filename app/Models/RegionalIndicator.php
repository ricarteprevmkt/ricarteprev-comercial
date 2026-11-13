<?php

namespace App\Models;

use App\Enums\IndicatorStatus;
use App\Models\Catalogs\ReferralChannel;
use Carbon\CarbonImmutable;
use Database\Factories\RegionalIndicatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Indicador regional: morador ou parceiro local que indica potenciais clientes.
 * O acesso ao sistema (user_id) é opcional.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $photo_path
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property int|null $city_id
 * @property string|null $neighborhood
 * @property int|null $region_id
 * @property int|null $referral_channel_id
 * @property int|null $commission_rule_id
 * @property CarbonImmutable $registered_at
 * @property IndicatorStatus $status
 * @property string|null $notes
 * @property-read User|null $user
 * @property-read City|null $city
 * @property-read Region|null $region
 * @property-read CommissionRule|null $commissionRule
 * @property-read ReferralChannel|null $referralChannel
 */
class RegionalIndicator extends Model
{
    /** @use HasFactory<RegionalIndicatorFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'name', 'photo_path', 'phone', 'whatsapp', 'email', 'city_id', 'neighborhood',
        'region_id', 'referral_channel_id', 'commission_rule_id', 'registered_at', 'status', 'notes',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'registered_at' => 'immutable_date',
            'status' => IndicatorStatus::class,
        ];
    }

    /** Tem acesso ao sistema agora (usuário vinculado e ativo). */
    public function hasActiveAccess(): bool
    {
        return $this->user_id !== null && (bool) $this->user?->active;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<CommissionRule, $this> */
    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    /** @return BelongsTo<ReferralChannel, $this> */
    public function referralChannel(): BelongsTo
    {
        return $this->belongsTo(ReferralChannel::class);
    }

    /**
     * @param  Builder<RegionalIndicator>  $query
     * @return Builder<RegionalIndicator>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', IndicatorStatus::Active->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('indicadores')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
