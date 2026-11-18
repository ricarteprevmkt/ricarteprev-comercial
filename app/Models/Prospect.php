<?php

namespace App\Models;

use App\Enums\ProspectStatus;
use App\Support\Phone;
use App\Support\Visibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interessado: contato que chegou (pelo WhatsApp do C2) e ainda não entrou no
 * funil. Vira contato e oportunidade quando é qualificado.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $phone_key
 * @property string|null $origin
 * @property string|null $demand
 * @property string|null $notes
 * @property string|null $c2_owner
 * @property string|null $c2_stage
 * @property CarbonImmutable $entered_at
 * @property ProspectStatus $status
 * @property int|null $executive_id
 * @property CarbonImmutable|null $assigned_at
 * @property int|null $lead_id
 * @property string|null $discard_reason
 * @property CarbonImmutable|null $qualified_at
 * @property CarbonImmutable|null $discarded_at
 * @property int|null $handled_by
 * @property CarbonImmutable|null $last_imported_at
 * @property-read Executive|null $executive
 * @property-read Lead|null $lead
 */
class Prospect extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name', 'phone', 'phone_key', 'origin', 'demand', 'notes', 'c2_owner', 'c2_stage', 'entered_at', 'status',
        'executive_id', 'assigned_at', 'lead_id', 'discard_reason', 'qualified_at', 'discarded_at', 'handled_by', 'last_imported_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'status' => ProspectStatus::class,
            'entered_at' => 'immutable_date',
            'assigned_at' => 'immutable_datetime',
            'qualified_at' => 'immutable_datetime',
            'discarded_at' => 'immutable_datetime',
            'last_imported_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Executive, $this> */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    /**
     * Quem vê tudo vê todos; o executivo vê só os atribuídos a ele.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('prospects.view_all')) {
            return;
        }

        $ids = $user->can('prospects.view_own') ? (Visibility::executiveIds($user) ?? []) : [];
        $query->whereIn('prospects.executive_id', $ids ?: [0]);
    }

    /** Chave de comparação: DDD + 8 últimos dígitos (com ou sem o 9 do celular). */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = Phone::normalize($phone);

        return $digits !== null && in_array(strlen($digits), [10, 11], true)
            ? substr($digits, 0, 2).substr($digits, -8)
            : null;
    }

    /** Nome legível: o nome do WhatsApp às vezes é só emoji. */
    public function displayName(): string
    {
        return preg_match('/\p{L}{2,}/u', $this->name) ? $this->name : 'Sem nome ('.$this->name.')';
    }
}
