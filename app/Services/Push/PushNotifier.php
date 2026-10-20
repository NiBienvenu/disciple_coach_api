<?php

namespace App\Services\Push;

use App\Models\User;

/**
 * App-level push events. Each push is sent after the HTTP response is
 * returned, so the sender never waits on Google.
 */
class PushNotifier
{
    public function __construct(private readonly FcmService $fcm) {}

    /**
     * @param  iterable<User>|User|null  $to
     * @param  array<string, scalar|null>  $data
     */
    public function notify(iterable|User|null $to, string $title, string $body, array $data = []): void
    {
        if ($to === null || ! $this->fcm->isConfigured()) {
            return;
        }

        $fcm = $this->fcm;
        dispatch(function () use ($fcm, $to, $title, $body, $data): void {
            $fcm->sendToUsers($to, $title, $body, $data);
        })->afterResponse();
    }
}
