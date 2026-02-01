<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_fcm_token_is_linked_to_current_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/fcm-token', [
                'fcm_token' => 'test-token-session',
                'device_id' => 'device-1',
            ]);

        $response->assertStatus(200);

        $device = UserDevice::where('fcm_token', 'test-token-session')->first();
        $this->assertNotNull($device->access_token_id);
    }

    public function test_fcm_token_is_unlinked_on_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        // Wir rufen update-fcm-token mit dem echten Token auf
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/fcm-token', [
                'fcm_token' => 'logout-token',
                'device_id' => 'device-logout',
            ])->assertStatus(200);

        $this->assertDatabaseHas('user_devices', [
            'fcm_token' => 'logout-token',
            'device_id' => 'device-logout',
        ]);

        $device = UserDevice::where('fcm_token', 'logout-token')->first();
        $this->assertNotNull($device->access_token_id);

        // Logout
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout')
            ->assertStatus(200);

        // Prüfen ob access_token_id jetzt null ist
        $device->refresh();
        $this->assertNull($device->access_token_id);
    }

    public function test_route_notification_only_returns_tokens_with_active_session(): void
    {
        $user = User::factory()->create();

        // Gerät 1: Aktive Session
        $token1 = $user->createToken('device-1');
        UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'token-1',
            'access_token_id' => $token1->accessToken->id
        ]);

        // Gerät 2: Keine aktive Session (z.B. nach Logout)
        UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'token-2',
            'access_token_id' => null
        ]);

        $fcmTokens = $user->routeNotificationForFcm();

        $this->assertContains('token-1', $fcmTokens);
        $this->assertNotContains('token-2', $fcmTokens);
        $this->assertCount(1, $fcmTokens);
    }

    public function test_fcm_token_must_be_unique(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UserDevice::create([
            'user_id' => $user1->id,
            'fcm_token' => 'unique-token',
            'device_id' => 'device-1'
        ]);

        $token = $user2->createToken('test-device-2')->plainTextToken;

        // Sollte funktionieren, da wir in updateFcmToken den Token bei anderen löschen
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/fcm-token', [
                'fcm_token' => 'unique-token',
                'device_id' => 'device-2',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('user_devices', 1);
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user2->id,
            'fcm_token' => 'unique-token'
        ]);
    }
}
