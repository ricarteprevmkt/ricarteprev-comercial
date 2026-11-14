<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $opportunity_id
 * @property CarbonImmutable $presented_at
 * @property string|null $amount
 * @property string|null $description
 * @property int|null $created_by
 */
class Proposal extends Model
{
    /** @var list<string> */
    protected $fillable = ['opportunity_id', 'presented_at', 'amount', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['presented_at' => 'immutable_date', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
