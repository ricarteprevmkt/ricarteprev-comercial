<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Notifications\Notification;

/**
 * Aviso interno ao executivo: chegou uma indicação para ele atender.
 */
class ReferralAssigned extends Notification
{
    public function __construct(private Referral $referral) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'referral_assigned',
            'message' => 'Nova indicação de '.$this->referral->indicator->name.': '.$this->referral->lead->name,
            'url' => route('referrals.list', ['busca' => $this->referral->lead->name]),
            'referral_id' => $this->referral->id,
        ];
    }
}
