<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Parâmetros comerciais configuráveis pelo administrador (fator de estrada,
 * custo por km, dias para considerar uma oportunidade parada etc.).
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property string $group
 * @property string $label
 * @property string|null $description
 */
#[Fillable(['key', 'value', 'type', 'group', 'label', 'description', 'updated_by'])]
class SystemSetting extends Model
{
    use LogsActivity;

    private const CACHE_KEY = 'system_settings.all';

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var array<string, array{value: mixed, type: string}> $all */
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get(['key', 'value', 'type'])
            ->mapWithKeys(fn (self $s) => [$s->key => ['value' => $s->value, 'type' => $s->type]])
            ->all());

        if (! array_key_exists($key, $all) || $all[$key]['value'] === null) {
            return $default;
        }

        return self::castValue($all[$key]['value'], $all[$key]['type']);
    }

    public static function put(string $key, mixed $value, ?int $userId = null): void
    {
        static::query()->where('key', $key)->firstOrFail()->update([
            'value' => $value,
            'updated_by' => $userId,
        ]);
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            // Valores decimais são gravados e devolvidos como string, sem passar por float.
            'decimal' => (string) $value,
            default => $value,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('parametros')->logOnly(['value'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
