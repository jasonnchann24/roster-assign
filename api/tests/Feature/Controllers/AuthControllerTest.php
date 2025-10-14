<?php

namespace Tests\Feature\Controllers;

use App\Http\Requests\SupplierFormRequest;
use App\Models\Supplier;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected JWTService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JWTService::class);
    }

    public function test_protected_routes_use_jwt_access_middleware()
    {
        $this->assertRouteUsesMiddleware('api.auth.logout', ['jwt.access']);
        $this->assertRouteUsesMiddleware('api.auth.me', ['jwt.access']);
    }

    public function test_it_should_use_form_request()
    {
        $this->assertRouteUsesFormRequest('api.register', SupplierFormRequest::class);
    }

    public function test_it_can_register_a_new_supplier()
    {
        $supplierData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $supplierData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('suppliers', [
            'email' => $supplierData['email'],
            'name' => $supplierData['name'],
        ]);
    }

    public function test_it_can_login_with_valid_credentials()
    {
        $supplier = Supplier::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                    'access_token',
                    'token_type',
                    'expires_in',
                ]
            ])
            ->assertHeader('X-Refresh-Token');

        $this->assertEquals('success', $response->json('status'));
        $this->assertEquals('Bearer', $response->json('data.token_type'));
        $this->assertEquals(1800, $response->json('data.expires_in'));
    }

    public function test_it_returns_error_for_invalid_login_credentials()
    {
        $supplier = Supplier::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_it_can_refresh_tokens_with_valid_tokens()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'X-Refresh-Token' => $tokenData['refresh_token'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'user',
                    'access_token',
                    'token_type',
                    'expires_in',
                ]
            ])
            ->assertHeader('X-Refresh-Token');
    }

    public function test_it_returns_error_when_refresh_token_is_missing()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'error' => 'Refresh token missing'
            ]);
    }

    public function test_it_returns_error_for_invalid_refresh_token()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
            'X-Refresh-Token' => 'invalid_refresh_token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error' => 'Invalid or expired refresh token'
            ]);
    }

    public function test_it_can_logout_and_revoke_tokens()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        // Verify token is in cache
        $cacheKey = "refresh_token_{$supplier->id}";
        $this->assertTrue(Cache::tags(['refresh_tokens'])->has($cacheKey));

        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully logged out']);

        // Verify token is removed from cache
        $this->assertFalse(Cache::tags(['refresh_tokens'])->has($cacheKey));
    }

    public function test_it_can_get_authenticated_user_profile()
    {
        $supplier = Supplier::factory()->create();
        $tokenData = $this->jwtService->generateTokenPair($supplier);

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $tokenData['access_token'],
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
            ]);
    }

    public function test_it_validates_registration_data()
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_it_validates_login_data()
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_it_prevents_duplicate_email_registration()
    {
        $existingSupplier = Supplier::factory()->create([
            'email' => 'test@example.com'
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'New Supplier',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
