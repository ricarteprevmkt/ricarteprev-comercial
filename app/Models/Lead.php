<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Catalogs\LeadSource;
use App\Models\Concerns\ScopedToExecutive;
use App\Support\Document;
use Carbon\CarbonImmutable;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Contato/lead. CPF ou CNPJ fica criptografado; o hash impede duplicidade.
 *
 * @property int $id
 * @property LeadType $type
 * @property string $name
 * @property string|null $document_encrypted
 * @property string|null $document_hash
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property int|null $city_id
 * @property string|null $neighborhood
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $lead_source_id
 * @property int|null $executive_id
 * @property int|null $regional_indicator_id
 * @property CarbonImmutable $entered_at
 * @property LeadStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read City|null $city
 * @property-read LeadSource|null $source
 * @property-read Executive|null $executive
 * @property-read RegionalIndicator|null $indicator
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, LogsActivity, ScopedToExecutive, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'type', 'name', 'phone', 'whatsapp', 'email', 'city_id', 'neighborhood', 'address',
        'lead_source_id', 'executive_id', 'regional_indicator_id', 'entered_at', 'status', 'notes', 'created_by',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['type' => 'pf', 'status' => 'active'];

    /** @var list<string> */
    protected $hidden = ['document_encrypted', 'document_hash'];

    protected function casts(): array
    {
        return [
            'type' => LeadType::class,
            'status' => LeadStatus::class,
            'document_encrypted' => 'encrypted',
            'entered_at' => 'immutable_date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function setDocument(?string $document): void
    {
        $digits = Document::digits($document);
        $this->document_encrypted = $digits === '' ? null : $digits;
        $this->document_hash = Document::hash($digits);
    }

    public function document(): ?string
    {
        return $this->document_encrypted;
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /** @return BelongsTo<Executive, $this> */
    public function executive(): BelongsTo
    {
        return $this->belongsTo(Executive::class);
    }

    /** @return BelongsTo<RegionalIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(RegionalIndicator::class, 'regional_indicator_id');
    }

    /** @return HasMany<Opportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /** @return HasMany<Visit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->latest('scheduled_at');
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Documento fica fora do fillable: nunca entra no log.
        return LogOptions::defaults()->useLogName('contatos')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
