<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Bell + dropdown notification centre (Phase 3 Slice 1), embedded once in
 * the shared authenticated layout for all three portals -- notifications
 * are role-agnostic (Illuminate\Notifications\Notifiable on User, see
 * app/Notifications/*), so one component serves admin/buyer/vendor alike
 * rather than three near-identical copies.
 *
 * Deliberately re-mounted fresh on every full page load: the unread count
 * and recent list are simply requeried each render, which is exactly
 * "updates on page load/navigation" as specified for this slice. Live,
 * no-reload updates are Phase 5 (Reverb) -- out of scope here.
 */
class NotificationCenter extends Component
{
    private const RECENT_LIMIT = 15;

    /**
     * Marks one of the current user's own notifications read, then sends
     * them to wherever it points. Scoped through the user's own
     * notifications() relation -- never a raw DatabaseNotification::find()
     * -- so there is no way to mark, or even detect the existence of,
     * another user's notification by guessing its id.
     */
    public function markAsRead(string $notificationId): mixed
    {
        $notification = Auth::user()->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            return null;
        }

        $notification->markAsRead();

        return redirect($notification->data['url']);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    /**
     * Renders the stored, structured `data` array (never frozen text --
     * see lang/en/notifications.php's `types` section) as the sentence
     * shown in the panel. The one piece of real logic in this component,
     * kept out of the Blade view: buyer_price needs thousands-separator
     * formatting that a plain __() replacement won't do for us.
     */
    public function notificationText(DatabaseNotification $notification): string
    {
        $params = $notification->data;

        if (isset($params['buyer_price'])) {
            $params['buyer_price'] = number_format($params['buyer_price']);
        }

        return __('notifications.types.'.$params['type'], $params);
    }

    public function render(): View
    {
        return view('livewire.notification-center', [
            'notifications' => Auth::user()->notifications()->latest()->limit(self::RECENT_LIMIT)->get(),
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ]);
    }
}
