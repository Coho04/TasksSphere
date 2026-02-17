<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestFcmNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestFcmNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test-notification {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test push notification to a specific user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);

        if (! $user) {
            $this->error("Benutzer mit ID {$userId} nicht gefunden.");

            return 1;
        }

        $tokens = $user->routeNotificationForFcm();

        if (empty($tokens)) {
            $this->warn("Der Benutzer {$user->email} hat keine registrierten FCM-Tokens.");
            $this->info('Hinweis: Tokens werden registriert, wenn sich der Benutzer über die mobile App (WebView) anmeldet oder den Header X-FCM-Token mitsendet.');

            return 1;
        }

        $this->info("Sende Test-Benachrichtigung an {$user->email}...");
        $this->info('Anzahl der Zielgeräte: '.(is_array($tokens) ? count($tokens) : 1));

        try {
            $user->notify(new TestFcmNotification);
            $this->info('Die Benachrichtigung wurde erfolgreich an die Warteschlange von Firebase übergeben.');
        } catch (\Exception $e) {
            $this->error('Fehler beim Senden der Benachrichtigung: '.$e->getMessage());
            Log::error('FCM Test Error: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
