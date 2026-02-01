<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLoginFcmTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_saves_fcm_token_from_headers(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt($password = 'secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
            'device_name' => 'mobile-app',
        ], [
            'X-FCM-Token' => 'login-token-456',
            'X-Device-ID' => 'mobile-device-id'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'fcm_token' => 'login-token-456',
            'device_id' => 'mobile-device-id'
        ]);

        $this->assertEquals('login-token-456', $user->fresh()->fcm_token);
    }

    public function test_api_login_saves_fcm_token_from_body(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt($password = 'secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
            'device_name' => 'mobile-app',
            'fcm_token' => 'body-token-789',
            'device_id' => 'body-device-id'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'fcm_token' => 'body-token-789',
            'device_id' => 'body-device-id'
        ]);

        $this->assertEquals('body-token-789', $user->fresh()->fcm_token);
    }
}
