<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke test: boots the full Laravel application (service providers, config,
 * autoloading) without touching the database. If this passes, the app at least
 * wires up correctly. DB-backed feature tests require a real MySQL instance and
 * are out of scope for the current CI.
 */
class ExampleTest extends TestCase
{
    public function test_application_boots(): void
    {
        $this->assertTrue($this->app->bound('config'));
        $this->assertSame('testing', $this->app->environment());
    }

    public function test_core_routes_are_registered(): void
    {
        $routes = $this->app['router']->getRoutes();

        $this->assertGreaterThan(0, count($routes), 'Expected routes to be registered.');
    }
}
