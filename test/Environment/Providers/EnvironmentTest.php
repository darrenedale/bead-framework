<?php

declare(strict_types=1);

namespace BeadTests\Environment\Providers;

use Bead\Environment\Providers\Environment;
use BeadTests\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private Environment $env;

    public function setUp(): void
    {
        putenv("ENVIRONMENT_TEST_VARIABLE=value");
        $this->env = new Environment();
    }

    public function tearDown(): void
    {
        unset($this->env);
    }

    /**
     * Ensure get returns values from the environment and an empty string for a variable not set.
     */
    public function testGet()
    {
        self::assertEquals("value", $this->env->get("ENVIRONMENT_TEST_VARIABLE"));
        self::assertEquals("", $this->env->get("ENVIRONMENT_MISSING_TEST_VARIABLE"));
    }

    /**
     * Ensure get returns true for a set environment varaible and false for a variable not set.
     */
    public function testHas()
    {
        self::assertTrue($this->env->has("ENVIRONMENT_TEST_VARIABLE"));
        self::assertFalse($this->env->has("ENVIRONMENT_MISSING_TEST_VARIABLE"));
    }
}
