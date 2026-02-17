<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmTokenHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_creates_device_from_headers_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/', [
                'X-FCM-Token' => 'header-token-123',
                'X-Device-ID' => 'header-device-id',
            ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'fcm_token' => 'header-token-123',
            'device_id' => 'header-device-id',
        ]);

        $this->assertEquals('header-token-123', $user->fresh()->fcm_token);
    }

    public function test_middleware_updates_existing_device_from_headers(): void
    {
        $user = User::factory()->create();
        $user->devices()->create([
            'device_id' => 'header-device-id',
            'fcm_token' => 'old-token',
        ]);

        $this->actingAs($user)
            ->get('/', [
                'X-FCM-Token' => 'new-token',
                'X-Device-ID' => 'header-device-id',
            ]);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'header-device-id',
            'fcm_token' => 'new-token',
        ]);

        $this->assertDatabaseCount('user_devices', 1);
    }

    public function test_middleware_does_nothing_when_not_authenticated(): void
    {
        $this->get('/', [
            'X-FCM-Token' => 'header-token-123',
            'X-Device-ID' => 'header-device-id',
        ]);

        $this->assertDatabaseMissing('user_devices', [
            'fcm_token' => 'header-token-123',
        ]);
    }
}
