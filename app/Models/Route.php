<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rota estimada do dia de um executivo. É derivada da agenda e recalculada
 * por App\Actions\Agenda\BuildRoute sempre que a agenda do dia muda.
 *
 * @property int $id
 * @property int $executive_id
 * @property CarbonImmutable $date
 * @property string $km_estimated
 * @property int $stops_count
 * @property bool $has_approximate_points
 */
class Route extends Model
{
    /** @var list<string> */
    protected $fillable = ['executive_id', 'date', 'km_estimated', 'stops_count', 'has_approximate_points'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'km_estimated' => 'decimal:1', 'has_approximate_points' => 'boolean'];
    }

    /** @return BelongsTo<Executive, $this> */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class);
    }

    /** @return HasMany<RouteStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('sequence');
    }
}
