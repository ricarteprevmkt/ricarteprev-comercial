<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro imutável de uma mudança de etapa.
 *
 * @property int $id
 * @property int $opportunity_id
 * @property OpportunityStage|null $from_stage
 * @property OpportunityStage $to_stage
 * @property int|null $user_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
class OpportunityHistory extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['opportunity_id', 'from_stage', 'to_stage', 'user_id', 'note', 'created_at'];

    protected function casts(): array
    {
        return [
            'from_stage' => OpportunityStage::class,
            'to_stage' => OpportunityStage::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
