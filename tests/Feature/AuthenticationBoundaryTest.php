<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_credentials_cannot_replace_a_sanctum_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['generations:read']);
        $this->getJson('/api/v1/generations?api_token[]=0')->assertUnauthorized();
        $this->getJson('/api/v1/generations?api_token=0')->assertUnauthorized();
        $this->getJson('/api/v1/generations', ['Authorization' => 'Bearer 0'])->assertUnauthorized();
        $this->getJson('/api/v1/generations', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertOk();
        $this->assertFalse((bool) config('app.debug'));
        $this->assertNotContains('token', array_column(config('auth.guards'), 'driver'));
    }
}
