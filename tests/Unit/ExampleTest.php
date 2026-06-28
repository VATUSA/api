<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — does not boot the Laravel application, so it has no DB or
 * config dependencies. Exercises an autoloaded helper from app/Helpers.
 */
class ExampleTest extends TestCase
{
    public function test_generate_error_returns_standard_shape(): void
    {
        $error = generate_error('boom');

        $this->assertSame(['status' => 'error', 'msg' => 'boom'], $error);
    }
}
