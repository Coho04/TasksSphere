<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FcmTokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_fcm_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fcm-token', [
            'fcm_token' => 'test-token-123',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('test-token-123', $user->fresh()->fcm_token);
    }

    public function test_fcm_token_update_requires_authentication(): void
    {
        $response = $this->postJson('/api/fcm-token', [
            'fcm_token' => 'test-token-123',
        ]);

        $response->assertStatus(401);
    }
}
