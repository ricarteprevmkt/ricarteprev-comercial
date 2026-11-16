<?php

namespace App\Actions\Referrals;

use App\Actions\Crm\FindDuplicateLead;
use App\Enums\LeadStatus;
use App\Enums\ReferralStatus;
use App\Models\Catalogs\LeadSource;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Referral;
use App\Models\ReferralHistory;
use App\Models\RegionalIndicator;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\ReferralAssigned;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registra uma indicação.
 *
 * - Contato novo: cria o contato (origem "Indicações", "Indicado por" este
 *   parceiro) e a indicação vale para o parceiro.
 * - Contato já existente: a indicação fica registrada como duplicada e a
 *   atribuição não muda, exceto quando a indicação anterior válida passou da
 *   janela de atribuição (parâmetro) e o contato ainda não é cliente: aí a
 *   nova indicação assume a atribuição.
 *
 * @phpstan-type ReferralData array{name: string, phone: string, whatsapp?: string|null, city_id: int,
 *     neighborhood?: string|null, referred_at: string, referral_channel_id?: int|null,
 *     executive_id?: int|null, notes?: string|null, consent: bool}
 */
final class RegisterReferral
{
    public function __construct(private FindDuplicateLead $duplicates, private SyncReferralStatus $sync) {}

    /**
     * @param  ReferralData  $data
     */
    public function handle(RegionalIndicator $indicator, array $data, ?User $user): Referral
    {
        $referredAt = CarbonImmutable::parse($data['referred_at']);
        $executiveId = $data['executive_id'] ?? $indicator->default_executive_id;
        $existing = $this->duplicates->handle(null, $data['phone'], $data['whatsapp'] ?? null)['lead'] ?? null;

        $referral = DB::transaction(function () use ($indicator, $data, $user, $referredAt, $executiveId, $existing) {
            if ($existing === null) {
                $lead = new Lead(['created_by' => $user?->id]);
                $lead->fill([
                    'type' => 'pf',
                    'name' => $data['name'],
                    'phone' => Phone::normalize($data['phone']),
                    'whatsapp' => Phone::normalize($data['whatsapp'] ?? null),
                    'city_id' => $data['city_id'],
                    'neighborhood' => $data['neighborhood'] ?? null,
                    'lead_source_id' => LeadSource::query()->where('slug', 'indicacoes')->value('id'),
                    'executive_id' => $executiveId,
                    'regional_indicator_id' => $indicator->id,
                    'entered_at' => $referredAt->toDateString(),
                    'status' => LeadStatus::Active,
                ]);
                $lead->save();

                return $this->create($indicator, $lead, $data, $user, $executiveId, duplicate: false, duplicateOf: null);
            }

            $previous = Referral::query()->where('lead_id', $existing->id)->where('is_duplicate', false)
                ->orderByDesc('referred_at')->orderByDesc('id')->first();
            $window = (int) SystemSetting::get('referrals.attribution_window_days', 90);
            $expired = $previous !== null && $previous->referred_at->addDays($window)->lt($referredAt);
            $takesAttribution = $expired && $existing->status !== LeadStatus::Client;

            if (! $takesAttribution) {
                return $this->create($indicator, $existing, $data, $user, $existing->executive_id, duplicate: true, duplicateOf: $previous?->id);
            }

            $existing->forceFill(['regional_indicator_id' => $indicator->id])->save();
            Opportunity::query()->where('lead_id', $existing->id)->open()->update(['regional_indicator_id' => $indicator->id]);

            return $this->create($indicator, $existing, $data, $user, $existing->executive_id ?? $executiveId, duplicate: false, duplicateOf: null);
        });

        if (! $referral->is_duplicate) {
            $this->sync->sync($referral);
            $referral->executive?->user->notify(new ReferralAssigned($referral));
        }

        return $referral->refresh();
    }

    /**
     * @param  ReferralData  $data
     */
    private function create(RegionalIndicator $indicator, Lead $lead, array $data, ?User $user, ?int $executiveId, bool $duplicate, ?int $duplicateOf): Referral
    {
        $referral = new Referral([
            'regional_indicator_id' => $indicator->id,
            'lead_id' => $lead->id,
            'executive_id' => $executiveId,
            'referral_channel_id' => $data['referral_channel_id'] ?? $indicator->referral_channel_id,
            'referred_at' => $data['referred_at'],
            'is_duplicate' => $duplicate,
            'duplicate_of_referral_id' => $duplicateOf,
            'consent_confirmed' => $data['consent'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $user?->id,
        ]);
        $referral->status = ReferralStatus::Referred;
        $referral->furthest_rank = 1;
        $referral->save();

        ReferralHistory::query()->create([
            'referral_id' => $referral->id,
            'from_status' => null,
            'to_status' => ReferralStatus::Referred,
            'user_id' => $user?->id,
            'note' => $duplicate ? 'Contato já cadastrado: indicação registrada como duplicada' : 'Indicação registrada',
            'created_at' => now(),
        ]);

        return $referral;
    }
}
