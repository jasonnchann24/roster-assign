<?php

namespace Tests\Feature\Middlewares;

use App\Http\Middleware\JWTAccessMiddleware;
use App\Models\Supplier;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class JWTAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected JWTService $jwtService;
    protected JWTAccessMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JWTService::class);
        $this->middleware = new JWTAccessMiddleware();
    }

    public function test_it_allows_request_with_valid_access_token()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ]);

        $response->assertStatus(200);
    }

    public function test_it_rejects_request_without_token()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'Token not provided'
            ]);
    }

    public function test_it_rejects_request_with_invalid_token()
    {
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer invalid_token_here',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'Token is invalid'
            ]);
    }

    public function test_it_rejects_expired_access_token()
    {
        $supplier = Supplier::factory()->create();

        // Create a SECOND EXPIRED access token
        $expiredToken = JWTAuth::customClaims([
            'type' => 'access',
            'exp' => now()->addSecond()->timestamp
        ])->fromUser($supplier);

        $this->travel(2)->seconds(); // Ensure the token is expired

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $expiredToken,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'Token has expired'
            ]);
    }

    public function test_it_rejects_refresh_token_when_access_token_is_required()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $tokenData['refresh_token'],
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'Invalid token type. Access token required.'
            ]);
    }

    public function test_it_rejects_malformed_authorization_header()
    {
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'InvalidFormat token_here',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'Token not provided'
            ]);
    }

    public function test_it_rejects_token_for_non_existent_user()
    {
        // Create a supplier and get token
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        // Delete the supplier
        $supplier->delete();

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'User not found'
            ]);
    }

    public function test_it_allows_multiple_requests_with_same_valid_token()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $headers = ['Authorization' => 'Bearer ' . $tokenData['access_token']];

        // First request
        $response1 = $this->getJson('/api/auth/me', $headers);
        $response1->assertStatus(200);

        // Second request with same token
        $response2 = $this->getJson('/api/auth/me', $headers);
        $response2->assertStatus(200);

        // Both should return the same user data
        $this->assertEquals($response1->json(), $response2->json());
    }

    public function test_it_handles_different_token_formats()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        // Test with proper Bearer format
        $response1 = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ]);
        $response1->assertStatus(200);

        // Test with token only (should fail)
        $response2 = $this->getJson('/api/auth/me', [
            'Authorization' => $tokenData['access_token'],
        ]);
        $response2->assertStatus(401);
    }

    public function test_it_validates_token_structure()
    {
        $supplier = Supplier::factory()->create();

        // Create token without type claim
        $tokenWithoutType = JWTAuth::fromUser($supplier);

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $tokenWithoutType,
        ]);

        // Should fail because token doesn't have 'type' => 'access'
        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'Invalid token type. Access token required.'
            ]);
    }

    public function test_it_protects_all_routes_with_jwt_access_middleware()
    {
        // Test logout endpoint
        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);

        // Test me endpoint
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
    }
}
