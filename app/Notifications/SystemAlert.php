<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso interno genérico (sino de notificações). A "key" identifica o aviso
 * para não repetir o mesmo alerta no mesmo dia.
 */
class SystemAlert extends Notification
{
    public function __construct(
        public string $key,
        public string $message,
        public string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'system_alert', 'key' => $this->key, 'message' => $this->message, 'url' => $this->url];
    }
}
