<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\FcmService;
use Illuminate\Console\Command;

class PushCheckCommand extends Command
{
    protected $signature = 'push:check {email? : Also send a real test push to this user\'s devices}';

    protected $description = 'Check that push notifications (Firebase FCM) can be sent';

    public function handle(FcmService $fcm): int
    {
        $this->line('Credentials file: '.config('services.fcm.credentials'));

        $problem = $fcm->diagnose();
        if ($problem !== null) {
            $this->error($problem);

            return self::FAILURE;
        }
        $this->info('Firebase key and project are OK.');

        $email = $this->argument('email');
        if ($email === null) {
            return self::SUCCESS;
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
        }

        $tokens = DeviceToken::query()->where('user_id', $user->id)->pluck('token');
        if ($tokens->isEmpty()) {
            $this->warn('This user has no registered device. They must open the app while signed in and allow notifications.');

            return self::FAILURE;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            $sent += $fcm->sendToToken($token, 'DiscipleCoach', 'Test notification', ['type' => 'check']) ? 1 : 0;
        }
        $this->line("Sent to {$sent} of {$tokens->count()} device(s). Failures are in storage/logs/laravel.log.");

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }
}
