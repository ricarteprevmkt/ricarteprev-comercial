<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

/**
 * Sino de notificações internas (tabela "notifications" do Laravel).
 * Cada notificação guarda em data['url'] o registro relacionado.
 */
class NotificationBell extends Component
{
    public function open(string $id): void
    {
        $notification = $this->find($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    private function find(string $id): DatabaseNotification
    {
        /** @var DatabaseNotification */
        return auth()->user()->notifications()->whereKey($id)->firstOrFail();
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(8)->get(),
        ]);
    }
}
