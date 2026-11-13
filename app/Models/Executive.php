<?php

namespace App\Models;

use App\Enums\ExecutiveStatus;
use App\Support\Cpf;
use Carbon\CarbonImmutable;
use Database\Factories\ExecutiveFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Executivo comercial. Os dados de acesso (nome, e-mail, telefone) ficam no
 * usuário vinculado; aqui ficam os dados comerciais.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $cpf_encrypted
 * @property string|null $cpf_hash
 * @property string|null $photo_path
 * @property int|null $city_id
 * @property int|null $region_id
 * @property CarbonImmutable|null $hired_at
 * @property ExecutiveStatus $status
 * @property int|null $monthly_visits_target
 * @property int|null $monthly_contracts_target
 * @property string|null $cost_allowance
 * @property string|null $compensation_notes
 * @property-read User $user
 * @property-read City|null $city
 * @property-read Region|null $region
 */
class Executive extends Model
{
    /** @use HasFactory<ExecutiveFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'photo_path', 'city_id', 'region_id', 'hired_at', 'status',
        'monthly_visits_target', 'monthly_contracts_target', 'cost_allowance', 'compensation_notes',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active'];

    /** @var list<string> */
    protected $hidden = ['cpf_encrypted', 'cpf_hash'];

    protected function casts(): array
    {
        return [
            'cpf_encrypted' => 'encrypted',
            'hired_at' => 'immutable_date',
            'status' => ExecutiveStatus::class,
            'monthly_visits_target' => 'integer',
            'monthly_contracts_target' => 'integer',
            'cost_allowance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Desligado perde o acesso; voltar a ativo ou afastado devolve.
        static::saved(function (Executive $executive) {
            if ($executive->wasChanged('status') || $executive->wasRecentlyCreated) {
                $executive->user()->update(['active' => $executive->status->allowsLogin()]);
            }
        });
    }

    public function setCpf(?string $cpf): void
    {
        $digits = Cpf::digits($cpf);
        $this->cpf_encrypted = $digits === '' ? null : $digits;
        $this->cpf_hash = Cpf::hash($digits);
    }

    public function cpf(): ?string
    {
        return $this->cpf_encrypted;
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

    /** @return HasMany<Goal, $this> */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Metas do mês: a específica, se houver, ou a padrão do executivo.
     *
     * @return array{visits: int|null, contracts: int|null, source: 'month'|'default'|'none'}
     */
    public function targetsFor(CarbonImmutable $month): array
    {
        $goal = $this->goals()->whereDate('month', $month->startOfMonth()->toDateString())->first();

        if ($goal !== null) {
            return ['visits' => $goal->visits_target, 'contracts' => $goal->contracts_target, 'source' => 'month'];
        }

        if ($this->monthly_visits_target !== null || $this->monthly_contracts_target !== null) {
            return ['visits' => $this->monthly_visits_target, 'contracts' => $this->monthly_contracts_target, 'source' => 'default'];
        }

        return ['visits' => null, 'contracts' => null, 'source' => 'none'];
    }

    /**
     * @param  Builder<Executive>  $query
     * @return Builder<Executive>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ExecutiveStatus::Active->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // O CPF fica fora do fillable e, portanto, nunca vai para o log de auditoria.
        return LogOptions::defaults()->useLogName('executivos')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
