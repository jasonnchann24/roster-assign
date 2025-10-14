<?php

namespace Tests\Unit\Services;

use App\Models\Supplier;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class JWTServiceTest extends TestCase
{
    use RefreshDatabase;

    protected JWTService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JWTService::class);
    }

    public function test_it_generates_token_pair_with_correct_structure()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $this->assertArrayHasKey('access_token', $tokenData);
        $this->assertArrayHasKey('refresh_token', $tokenData);
        $this->assertArrayHasKey('token_type', $tokenData);
        $this->assertArrayHasKey('expires_in', $tokenData);

        $this->assertEquals('Bearer', $tokenData['token_type']);
        $this->assertEquals(1800, $tokenData['expires_in']); // 30 minutes
    }

    public function test_it_generates_access_token_with_correct_claims()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        JWTAuth::setToken($tokenData['access_token']);
        $payload = JWTAuth::getPayload();

        $this->assertEquals('access', $payload->get('type'));
        $this->assertEquals($supplier->id, $payload->get('sub'));
        $this->assertNotNull($payload->get('exp'));
        $this->assertNotNull($payload->get('iat'));
    }

    public function test_it_generates_refresh_token_with_correct_claims()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        JWTAuth::setToken($tokenData['refresh_token']);
        $payload = JWTAuth::getPayload();

        $this->assertEquals('refresh', $payload->get('type'));
        $this->assertEquals($supplier->id, $payload->get('sub'));
        $this->assertNotNull($payload->get('jti'));
        $this->assertNotNull($payload->get('exp'));
    }

    public function test_it_stores_hashed_refresh_token_in_cache()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $cacheKey = "refresh_token_{$supplier->id}";
        $storedToken = Cache::tags(['refresh_tokens'])->get($cacheKey);

        $this->assertNotNull($storedToken);
        $this->assertEquals(hash('sha256', $tokenData['refresh_token']), $storedToken);
    }

    public function test_it_replaces_existing_refresh_token()
    {
        $supplier = Supplier::factory()->create();

        // Generate first token pair
        $firstTokenData = $this->jwtService->generateTokenPair($supplier);
        $cacheKey = "refresh_token_{$supplier->id}";
        $firstStoredToken = Cache::tags(['refresh_tokens'])->get($cacheKey);

        // Generate second token pair
        $secondTokenData = $this->jwtService->generateTokenPair($supplier);
        $secondStoredToken = Cache::tags(['refresh_tokens'])->get($cacheKey);

        // Should be different tokens
        $this->assertNotEquals($firstStoredToken, $secondStoredToken);
        $this->assertEquals(hash('sha256', $secondTokenData['refresh_token']), $secondStoredToken);
    }

    public function test_it_refreshes_tokens_with_valid_tokens()
    {
        $supplier = Supplier::factory()->create();
        $originalTokenData = $this->jwtService->generateTokenPair($supplier);

        $newTokenData = $this->jwtService->refreshTokens(
            $originalTokenData['access_token'],
            $originalTokenData['refresh_token']
        );

        $this->assertNotNull($newTokenData);
        $this->assertArrayHasKey('access_token', $newTokenData);
        $this->assertArrayHasKey('refresh_token', $newTokenData);

        // New tokens should be different from original
        $this->assertNotEquals($originalTokenData['access_token'], $newTokenData['access_token']);
        $this->assertNotEquals($originalTokenData['refresh_token'], $newTokenData['refresh_token']);
    }

    public function test_it_removes_used_refresh_token_after_refresh()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);
        $cacheKey = "refresh_token_{$supplier->id}";

        // Verify token exists
        $this->assertTrue(Cache::tags(['refresh_tokens'])->has($cacheKey));

        // Refresh tokens
        $this->jwtService->refreshTokens($tokenData['access_token'], $tokenData['refresh_token']);

        // Old refresh token should be replaced with new one
        $newStoredToken = Cache::tags(['refresh_tokens'])->get($cacheKey);
        $this->assertNotEquals(hash('sha256', $tokenData['refresh_token']), $newStoredToken);
    }

    public function test_it_returns_null_for_invalid_access_token_on_refresh()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $result = $this->jwtService->refreshTokens('invalid_access_token', $tokenData['refresh_token']);

        $this->assertNull($result);
    }

    public function test_it_returns_null_for_invalid_refresh_token()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $result = $this->jwtService->refreshTokens($tokenData['access_token'], 'invalid_refresh_token');

        $this->assertNull($result);
    }

    public function test_it_returns_null_for_expired_refresh_token()
    {
        $supplier = Supplier::factory()->create();

        // Create a SECOND EXPIRED refresh token
        $expiredRefreshToken = JWTAuth::customClaims([
            'type' => 'refresh',
            'jti' => uniqid(),
            'exp' => now()->addSecond()->timestamp
        ])->fromUser($supplier);

        $this->travel(2)->seconds(); // Ensure the token is expired

        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $result = $this->jwtService->refreshTokens($tokenData['access_token'], $expiredRefreshToken);

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_tokens_belong_to_different_users()
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        $tokenData1 = $this->jwtService->generateTokenPair($supplier1);
        $tokenData2 = $this->jwtService->generateTokenPair($supplier2);

        $result = $this->jwtService->refreshTokens($tokenData1['access_token'], $tokenData2['refresh_token']);

        $this->assertNull($result);
    }

    public function test_it_revokes_tokens_for_user()
    {
        $supplier = Supplier::factory()->create();
        $this->jwtService->generateTokenPair($supplier);

        $cacheKey = "refresh_token_{$supplier->id}";
        $this->assertTrue(Cache::tags(['refresh_tokens'])->has($cacheKey));

        $this->jwtService->revokeTokens($supplier);

        $this->assertFalse(Cache::tags(['refresh_tokens'])->has($cacheKey));
    }

    public function test_it_revokes_all_refresh_tokens()
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        $this->jwtService->generateTokenPair($supplier1);
        $this->jwtService->generateTokenPair($supplier2);

        $cacheKey1 = "refresh_token_{$supplier1->id}";
        $cacheKey2 = "refresh_token_{$supplier2->id}";

        $this->assertTrue(Cache::tags(['refresh_tokens'])->has($cacheKey1));
        $this->assertTrue(Cache::tags(['refresh_tokens'])->has($cacheKey2));

        $this->jwtService->revokeAllRefreshTokens();

        $this->assertFalse(Cache::tags(['refresh_tokens'])->has($cacheKey1));
        $this->assertFalse(Cache::tags(['refresh_tokens'])->has($cacheKey2));
    }
}
