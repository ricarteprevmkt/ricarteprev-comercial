<?php

namespace App\Models;

use App\Enums\ExpenseKind;
use App\Enums\ExpenseStatus;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Concerns\ScopedToExecutive;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Despesa da operação comercial. Entra nos indicadores de custo só quando
 * efetiva e aprovada (veja docs/metricas.md).
 *
 * @property int $id
 * @property string $description
 * @property int $expense_category_id
 * @property string $amount
 * @property CarbonImmutable $spent_on
 * @property int|null $executive_id
 * @property int|null $city_id
 * @property int|null $region_id
 * @property int|null $visit_id
 * @property ExpenseKind $kind
 * @property ExpenseStatus $status
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $rejection_reason
 * @property-read ExpenseCategory $category
 * @property-read Executive|null $executive
 * @property-read Visit|null $visit
 * @property-read City|null $city
 */
class Expense extends Model
{
    use LogsActivity, ScopedToExecutive, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'description', 'expense_category_id', 'amount', 'spent_on', 'executive_id', 'city_id', 'region_id',
        'visit_id', 'kind', 'created_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => 'actual', 'status' => 'pending'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'immutable_date',
            'kind' => ExpenseKind::class,
            'status' => ExpenseStatus::class,
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<Executive, $this> */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class)->withTrashed();
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('despesas')
            ->logOnly(['description', 'expense_category_id', 'amount', 'spent_on', 'executive_id', 'kind', 'status', 'approved_by', 'rejection_reason'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
