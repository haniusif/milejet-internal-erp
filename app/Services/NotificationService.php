<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;

/**
 * Persists an in-app notification and pushes it to the user's registered
 * devices (FCM). Push is a no-op until FCM credentials are configured.
 */
class NotificationService
{
    public function __construct(protected PushService $push) {}

    public function notify(User $user, string $type, string $title, ?string $body = null, array $data = []): Notification
    {
        $note = Notification::create([
            'user_id' => $user->id,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data ?: null,
        ]);

        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
        if ($tokens) {
            $result = $this->push->send($tokens, $title, $body ?? '', ['type' => $type] + $data);
            if (!empty($result['invalid'])) {
                DeviceToken::whereIn('token', $result['invalid'])->delete(); // prune dead tokens
            }
        }
        return $note;
    }

    /** Notify the user linked to an Odoo employee id, if one exists. */
    public function notifyEmployee(int $odooEmployeeId, string $type, string $title, ?string $body = null, array $data = []): ?Notification
    {
        $user = User::where('odoo_employee_id', $odooEmployeeId)->first();
        return $user ? $this->notify($user, $type, $title, $body, $data) : null;
    }
}
