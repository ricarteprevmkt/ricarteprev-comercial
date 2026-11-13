<?php

namespace App\Models\Catalogs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Item de catálogo configurável (origem de contato, motivo de perda etc.).
 * O slug é gerado do nome na criação e não muda depois, para que regras do
 * sistema possam se referir a um item específico com segurança.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $active
 */
abstract class CatalogItem extends Model
{
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'sort_order', 'active'];

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (CatalogItem $item) {
            $item->slug = $item->slug ?: static::uniqueSlug($item->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('catalogos')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
