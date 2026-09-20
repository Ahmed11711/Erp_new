<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthTokenRefreshTest extends TestCase
{
    use DatabaseTransactions;

    public function test_refresh_issues_a_new_token_after_access_ttl_expires(): void
    {
        $user = User::factory()->create([
            'department' => 'Test',
            'password' => bcrypt('secret12'),
        ]);

        $token = auth('api')->login($user);
        $this->assertIsString($token);

        $this->travel(6001)->minutes();

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
        $this->assertNotSame($token, $response->json('access_token'));
    }

    public function test_refresh_rejects_a_missing_token(): void
    {
        $this->postJson('/api/auth/refresh')->assertStatus(401);
    }
}
