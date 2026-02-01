<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\TaskReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_command_sends_notifications_for_due_tasks(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $user->createToken('test-device');
        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_id' => 'device-123',
            'fcm_token' => 'fake-fcm-token',
            'access_token_id' => $token->accessToken->id,
        ]);

        $task = Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Due Task',
            'due_at' => now()->subMinute(),
            'completed_at' => null,
            'last_notified_at' => null,
        ]);

        $this->artisan('tasks:send-reminders')
            ->expectsOutput('Gefundene fällige Aufgaben: 1')
            ->expectsOutput("Benachrichtigung für Task ID {$task->id} an Benutzer {$user->email} gesendet.")
            ->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            TaskReminderNotification::class,
            function ($notification, $channels) use ($task) {
                return $channels === ['NotificationChannels\Fcm\FcmChannel'] || in_array('NotificationChannels\Fcm\FcmChannel', $channels);
            }
        );

        $task->refresh();
        $this->assertNotNull($task->last_notified_at);
    }

    public function test_reminder_command_does_not_send_duplicate_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $user->createToken('test-device');
        UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'fake-fcm-token',
            'access_token_id' => $token->accessToken->id,
        ]);

        $dueAt = now()->subMinutes(10);
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'due_at' => $dueAt,
            'last_notified_at' => $dueAt->addMinute(), // Already notified after due_at
        ]);

        $this->artisan('tasks:send-reminders')
            ->expectsOutput('Gefundene fällige Aufgaben: 0')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
