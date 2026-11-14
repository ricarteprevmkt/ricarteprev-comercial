<?php

namespace App\Models;

use App\Enums\VisitPriority;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Catalogs\VisitReason;
use App\Models\Concerns\ScopedToExecutive;
use Carbon\CarbonImmutable;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $executive_id
 * @property int|null $lead_id
 * @property int|null $opportunity_id
 * @property string|null $contact_name
 * @property VisitType $type
 * @property VisitPriority $priority
 * @property VisitStatus $status
 * @property CarbonImmutable $scheduled_at
 * @property string|null $address
 * @property int|null $city_id
 * @property string|null $neighborhood
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $location_source
 * @property string|null $notes
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string|null $outcome
 * @property int|null $not_done_reason_id
 * @property string|null $next_action
 * @property CarbonImmutable|null $next_contact_at
 * @property string|null $km_reported
 * @property string|null $checkin_latitude
 * @property string|null $checkin_longitude
 * @property CarbonImmutable|null $checkin_at
 * @property int|null $rescheduled_to_id
 * @property int|null $created_by
 * @property-read Executive $executive
 * @property-read Lead|null $lead
 * @property-read Opportunity|null $opportunity
 * @property-read City|null $city
 * @property-read VisitReason|null $notDoneReason
 */
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory, LogsActivity, ScopedToExecutive, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'executive_id', 'lead_id', 'opportunity_id', 'contact_name', 'type', 'priority', 'status', 'scheduled_at',
        'address', 'city_id', 'neighborhood', 'latitude', 'longitude', 'location_source', 'notes',
        'started_at', 'finished_at', 'outcome', 'not_done_reason_id', 'next_action', 'next_contact_at',
        'km_reported', 'checkin_latitude', 'checkin_longitude', 'checkin_at', 'rescheduled_to_id', 'created_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'scheduled', 'priority' => 'normal'];

    protected function casts(): array
    {
        return [
            'type' => VisitType::class,
            'priority' => VisitPriority::class,
            'status' => VisitStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'checkin_at' => 'immutable_datetime',
            'next_contact_at' => 'immutable_date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'checkin_latitude' => 'decimal:7',
            'checkin_longitude' => 'decimal:7',
            'km_reported' => 'decimal:1',
        ];
    }

    /** @return BelongsTo<Executive, $this> */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class)->withTrashed();
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class)->withTrashed();
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<VisitReason, $this> */
    public function notDoneReason(): BelongsTo
    {
        return $this->belongsTo(VisitReason::class);
    }

    /** @return BelongsTo<Visit, $this> */
    public function rescheduledTo(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'rescheduled_to_id');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function displayName(): string
    {
        return $this->lead->name ?? $this->contact_name ?? 'Sem contato';
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeOnDate(Builder $query, CarbonImmutable $date): Builder
    {
        return $query->whereBetween('scheduled_at', [$date->startOfDay(), $date->endOfDay()]);
    }

    /**
     * Visitas cujo horário passou do prazo sem resultado registrado.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeWithoutResult(Builder $query): Builder
    {
        $hours = (int) SystemSetting::get('visits.result_due_hours', 24);

        return $query->whereIn('status', [VisitStatus::Scheduled->value, VisitStatus::Confirmed->value, VisitStatus::InProgress->value])
            ->where('scheduled_at', '<', now()->subHours($hours));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('agenda')
            ->logOnly(['executive_id', 'lead_id', 'type', 'status', 'scheduled_at', 'outcome', 'not_done_reason_id', 'km_reported'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
