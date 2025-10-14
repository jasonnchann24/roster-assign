<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPUnit\Framework\Assert as PHPUnitAssert;
use JMac\Testing\Traits\AdditionalAssertions;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    use AdditionalAssertions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.cookie_mode' => false]);
    }

    public function assertRouteIsPublic(string $routeName, array $restrictedMiddlewares = ['auth', 'auth:sanctum', 'auth:api', 'jwt.access']): void
    {
        $router = resolve(\Illuminate\Routing\Router::class);

        $route = $router->getRoutes()->getByName($routeName);

        PHPUnitAssert::assertNotNull($route, "Unable to find route for name `$routeName`");

        $excludedMiddleware = $route->action['excluded_middleware'] ?? [];
        $usedMiddlewares = array_diff($route->gatherMiddleware(), $excludedMiddleware);

        $foundRestrictedMiddlewares = array_intersect($restrictedMiddlewares, $usedMiddlewares);

        PHPUnitAssert::assertTrue(count($foundRestrictedMiddlewares) === 0, "Route `$routeName` should be public but uses restricted `" . implode(', ', $foundRestrictedMiddlewares) . '` middleware(s)');
    }

    /**
     * Make an authenticated JSON request with JWT token
     */
    protected function authenticatedJson($method, $uri, array $data = [], array $headers = [], $user = null)
    {
        if ($user) {
            $token = JWTAuth::claims(['type' => 'access'])
                ->fromUser($user);
        } else {
            // If no user provided, try to use the current authenticated user
            $token = JWTAuth::claims(['type' => 'access'])
                ->fromUser($this->user ?? \App\Models\Supplier::factory()->create());
        }

        $headers['Authorization'] = 'Bearer ' . $token;
        return $this->json($method, $uri, $data, $headers);
    }
}
