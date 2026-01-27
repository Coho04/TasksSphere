<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FcmTokenUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_fcm_token_with_device_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fcm-token', [
            'fcm_token' => 'new-token-123',
            'device_id' => 'my-phone-uuid',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'my-phone-uuid',
            'fcm_token' => 'new-token-123',
        ]);

        $user->refresh();
        $this->assertEquals('new-token-123', $user->fcm_token);
    }

    public function test_user_can_update_multiple_devices(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // First device
        $this->postJson('/api/fcm-token', [
            'fcm_token' => 'token-phone',
            'device_id' => 'phone-id',
        ]);

        // Second device
        $this->postJson('/api/fcm-token', [
            'fcm_token' => 'token-tablet',
            'device_id' => 'tablet-id',
        ]);

        $this->assertEquals(2, $user->devices()->count());
        $this->assertEquals(['token-phone', 'token-tablet'], $user->devices()->pluck('fcm_token')->sort()->values()->toArray());
    }

    public function test_token_is_removed_from_other_users_when_reassigned(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Token belongs to user1
        UserDevice::create([
            'user_id' => $user1->id,
            'fcm_token' => 'shared-token',
            'device_id' => 'common-device',
        ]);

        Sanctum::actingAs($user2);

        // User2 claims the token
        $this->postJson('/api/fcm-token', [
            'fcm_token' => 'shared-token',
            'device_id' => 'common-device',
        ]);

        $this->assertDatabaseMissing('user_devices', ['user_id' => $user1->id, 'fcm_token' => 'shared-token']);
        $this->assertDatabaseHas('user_devices', ['user_id' => $user2->id, 'fcm_token' => 'shared-token']);
    }
}
