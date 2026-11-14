<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mudança de situação de uma indicação. user_id nulo = atualização automática
 * a partir do funil.
 *
 * @property int $id
 * @property int $referral_id
 * @property ReferralStatus|null $from_status
 * @property ReferralStatus $to_status
 * @property int|null $user_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 * @property-read Referral $referral
 */
class ReferralHistory extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['referral_id', 'from_status', 'to_status', 'user_id', 'note', 'created_at'];

    protected function casts(): array
    {
        return [
            'from_status' => ReferralStatus::class,
            'to_status' => ReferralStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Referral, $this> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
