<?php

namespace App\Services\Push;

use App\Models\User;

/**
 * App-level push events. Each push is sent after the HTTP response is
 * returned, so the sender never waits on Google. Texts are given per
 * language (['fr' => ..., 'en' => ...]) and each recipient gets theirs.
 */
class PushNotifier
{
    public function __construct(private readonly FcmService $fcm) {}

    /**
     * @param  iterable<User>|User|null  $to
     * @param  array<string, string>|string  $title
     * @param  array<string, string>|string  $body
     * @param  array<string, scalar|null>  $data
     */
    public function notify(iterable|User|null $to, array|string $title, array|string $body, array $data = []): void
    {
        if ($to === null || ! $this->fcm->isConfigured()) {
            return;
        }

        $users = $to instanceof User ? [$to] : $to;
        $fcm = $this->fcm;
        dispatch(function () use ($fcm, $users, $title, $body, $data): void {
            foreach ($users as $user) {
                if ($user === null) {
                    continue;
                }
                $lang = $user->preferred_language instanceof \BackedEnum
                    ? $user->preferred_language->value
                    : (string) $user->preferred_language;
                $fcm->sendToUsers($user, self::pick($title, $lang), self::pick($body, $lang), $data);
            }
        })->afterResponse();
    }

    /** Kirundi (or anything missing) falls back to French. */
    private static function pick(array|string $text, string $lang): string
    {
        if (is_string($text)) {
            return $text;
        }

        return $text[$lang] ?? $text['fr'] ?? (string) reset($text);
    }
}
