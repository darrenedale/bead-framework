<?php

declare(strict_types=1);

namespace BeadTests\Environment\Sources;

use Bead\Environment\Sources\Environment;
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
        parent::tearDown();
    }

    /** Ensure get returns values from the environment and an empty string for a variable not set. */
    public function testGet1(): void
    {
        self::assertEquals("value", $this->env->get("ENVIRONMENT_TEST_VARIABLE"));
        self::assertEquals("", $this->env->get("ENVIRONMENT_MISSING_TEST_VARIABLE"));
    }

    /** Ensure get returns true for a set environment varaible and false for a variable not set. */
    public function testHas1(): void
    {
        self::assertTrue($this->env->has("ENVIRONMENT_TEST_VARIABLE"));
        self::assertFalse($this->env->has("ENVIRONMENT_MISSING_TEST_VARIABLE"));
    }
}
