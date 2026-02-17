<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\TestFcmNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TestFcmNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_test_notification_successfully(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'fake-token',
            'device_id' => 'device-1',
        ]);

        $this->artisan('fcm:test-notification '.$user->id)
            ->expectsOutput("Sende Test-Benachrichtigung an {$user->email}...")
            ->expectsOutput('Die Benachrichtigung wurde erfolgreich an die Warteschlange von Firebase übergeben.')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            TestFcmNotification::class
        );
    }

    public function test_it_fails_if_user_not_found(): void
    {
        $this->artisan('fcm:test-notification 999')
            ->expectsOutput('Benutzer mit ID 999 nicht gefunden.')
            ->assertExitCode(1);
    }

    public function test_it_fails_if_no_tokens_found(): void
    {
        $user = User::factory()->create();

        $this->artisan('fcm:test-notification '.$user->id)
            ->expectsOutput("Der Benutzer {$user->email} hat keine registrierten FCM-Tokens.")
            ->assertExitCode(1);
    }
}
