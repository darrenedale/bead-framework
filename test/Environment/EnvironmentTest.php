<?php

declare(strict_types=1);

namespace BeadTests\Environment;

use Bead\AppLog;
use Bead\Environment\Environment;
use Bead\Environment\Providers\StaticArray;
use Bead\Exceptions\Environment\Exception;
use Bead\Testing\XRay;
use BeadTests\Environment\Providers\StaticArrayTest;
use BeadTests\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    /** @var string[] content for the test environment provider. */
    private const TestEnvironment = [
        "KEY_1" => "value-1",
    ];

    /** @var Environment The environment under test. */
    private Environment $env;

    public function setUp(): void
    {
        $this->env = new Environment();
        $this->env->addProvider(new StaticArray(self::TestEnvironment));
    }

    public function tearDown(): void
    {
        unset ($this->env);
        parent::tearDown();
    }

    /** Ensure we can add a provider. */
    public function testAddProvider()
    {
        $provider = new StaticArray([
            "KEY_1" => "value-2",
        ]);

        $this->env->addProvider($provider);
        $env = new XRay($this->env);
        self::assertCount(2, $env->providers);
        self::assertSame($provider, $env->providers[0]);
    }

    /** Ensure get() reads a value that exists in the environment. */
    public function testGetWithExisting()
    {
        self::assertEquals("value-1", $this->env->get("KEY_1"));
    }

    /** Ensure get() retruns an empty string for a key that does not exist in the environment. */
    public function testGetWithMissing()
    {
        self::assertEquals("", $this->env->get("KEY_2"));
    }

    /** Ensure get() returns the value from the provider with the highest precedence. */
    public function testGetProviderHonoursPrecedence(): void
    {
        $this->env->addProvider(new StaticArray([
            "KEY_1" => "value-2",
        ]));

        self::assertEquals("value-2", $this->env->get("KEY_1"));
    }

    /** Ensure get() uses providers with lower precedence when key missing in higher-precedence providers. */
    public function testGetProviderFallsBack(): void
    {
        $this->env->addProvider(new StaticArray([
            "KEY_2" => "value-2",
        ]));

        self::assertEquals("value-1", $this->env->get("KEY_1"));
    }

    /** Ensure has() returns true for a value that exists in the environment. */
    public function testHasWithExisting()
    {
        self::assertTrue($this->env->has("KEY_1"));
    }

    /** Ensure has() retruns false for a key that does not exist in the environment. */
    public function testHasWithMissing()
    {
        self::assertFalse($this->env->has("KEY_2"));
    }

    /** Ensure has() checks all providers if necessary. */
    public function testHasChecksAllProviders(): void
    {
        $this->env->addProvider(new StaticArray([
            "KEY_2" => "value-2",
        ]));

        self::assertTrue($this->env->has("KEY_1"));
        self::assertTrue($this->env->has("KEY_2"));
    }

    /** Ensure get() logs a warning if a provider throws. */
    public function testGetLogsExceptionMessage(): void
    {
        $messageLogged = false;

        $provider = new class([]) extends StaticArray
        {
            public function has(string $key): bool
            {
                throw new Exception("Unable to check for variable {$key}.");
            }
        };

        $logger = new class($messageLogged) extends AppLog {
            private bool $messageLogged;
            public function __construct(bool & $messageLogged)
            {
                parent::__construct("");
                $this->messageLogged =& $messageLogged;
            }

            public function isOpen(): bool
            {
                return true;
            }

            public function write(string $msg): bool
            {
                TestCase::assertMatchesRegularExpression("/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2} \\?\\?\\(\\?\\?\\) WRN Environment exception querying environment provider of type .*: Unable to check for variable KEY_1.\$/", $msg);
                $this->messageLogged = true;
                return true;
            }
        };

        AppLog::setWarningLog($logger);
        $this->env->addProvider($provider);
        self::assertEquals("value-1", $this->env->get("KEY_1"));
        self::assertTrue($messageLogged);
    }
}
