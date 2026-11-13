<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Município do IBGE. A lista vem pronta do seeder; o administrador só marca
 * as cidades atendidas e as agrupa em regiões.
 *
 * @property int $id
 * @property int $ibge_code
 * @property string $name
 * @property string $uf
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $region_id
 * @property bool $served
 */
#[Fillable(['region_id', 'served'])]
class City extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'ibge_code' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'served' => 'boolean',
        ];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    public function scopeServed(Builder $query): Builder
    {
        return $query->where('served', true);
    }

    public function label(): string
    {
        return "{$this->name}/{$this->uf}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('cidades')->logOnly(['region_id', 'served'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
