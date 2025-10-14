<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPUnit\Framework\Assert as PHPUnitAssert;
use JMac\Testing\Traits\AdditionalAssertions;

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
}
