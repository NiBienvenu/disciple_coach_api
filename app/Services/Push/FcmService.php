<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends push notifications through Firebase Cloud Messaging (HTTP v1 API).
 *
 * Auth uses a Google service account: we sign a JWT with its private key and
 * trade it for a short-lived OAuth token (cached ~55 min). No SDK needed.
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Push to every registered device of each user.
     *
     * @param  iterable<User>|User  $users
     * @param  array<string, scalar|null>  $data  Delivered to the app as string key/values.
     */
    public function sendToUsers(iterable|User $users, string $title, string $body, array $data = []): void
    {
        $users = $users instanceof User ? [$users] : $users;
        $ids = [];
        foreach ($users as $user) {
            if ($user !== null) {
                $ids[] = $user->id;
            }
        }
        if ($ids === [] || ! $this->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::query()->whereIn('user_id', $ids)->pluck('token');
        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            return false;
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = (string) ($value ?? '');
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => (object) $stringData,
                'android' => [
                    // High priority wakes the device right away (chat must feel instant).
                    'priority' => 'HIGH',
                    'notification' => [
                        'tag' => $stringData['tag'] ?? null,
                    ],
                ],
                'apns' => [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => ['aps' => ['sound' => 'default']],
                ],
            ],
        ];

        try {
            $response = Http::withToken($this->accessToken($credentials))
                ->timeout(8)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$this->projectId($credentials)}/messages:send",
                    $payload,
                );

            if ($response->successful()) {
                return true;
            }

            // Token no longer valid (app uninstalled / token rotated) → forget it.
            $status = $response->json('error.status');
            if ($response->status() === 404 || in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                DeviceToken::query()->where('token', $token)->delete();
            }
            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
            }

            Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->json()]);
        } catch (Throwable $e) {
            Log::warning('FCM send error: '.$e->getMessage());
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(55), function () use ($credentials) {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "{$header}.{$claims}.".$this->base64Url($signature);

            $response = Http::asForm()->timeout(8)->post(
                $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            )->throw();

            return (string) $response->json('access_token');
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        $path = (string) config('services.fcm.credentials');
        if ($path === '' || ! File::isFile($path)) {
            return null;
        }

        $json = json_decode(File::get($path), true);

        return is_array($json) && isset($json['client_email'], $json['private_key']) ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function projectId(array $credentials): string
    {
        return (string) ($credentials['project_id'] ?? config('services.fcm.project_id'));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
