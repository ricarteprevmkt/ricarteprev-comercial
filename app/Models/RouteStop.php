<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int $visit_id
 * @property int $sequence
 * @property string|null $leg_km
 */
class RouteStop extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['route_id', 'visit_id', 'sequence', 'leg_km'];

    protected function casts(): array
    {
        return ['leg_km' => 'decimal:1'];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
