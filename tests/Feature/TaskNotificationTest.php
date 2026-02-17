<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TaskReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_creation_triggers_notification_when_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Test Task',
            'notify' => true,
        ]);

        $response->assertStatus(201);

        Notification::assertSentTo(
            $user,
            TaskReminderNotification::class
        );
    }

    public function test_task_creation_does_not_trigger_notification_by_default(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Test Task',
        ]);

        $response->assertStatus(201);

        Notification::assertNothingSent();
    }
}
