<?php

declare(strict_types=1);

namespace BeadTests\Environment;

use Bead\AppLog;
use Bead\Contracts\Logger;
use Bead\Core\Application;
use Bead\Environment\Environment;
use Bead\Environment\Sources\StaticArray;
use Bead\Exceptions\Environment\Exception;
use Bead\Exceptions\EnvironmentException;
use Bead\Testing\XRay;
use BeadTests\Environment\Sources\StaticArrayTest;
use BeadTests\Framework\TestCase;
use Mockery;

final class EnvironmentTest extends TestCase
{
    /** @var string[] content for the test environment provider. */
    private const TestEnvironment = [
        "KEY_1" => "value-1",
        "KEY_3" => "third-value",
    ];

    /** @var Environment The environment under test. */
    private Environment $env;

    public function setUp(): void
    {
        $this->env = new Environment();
        $this->env->addSource(new StaticArray(self::TestEnvironment));
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->env);
        parent::tearDown();
    }

    /** Ensure we can add a provider. */
    public function testAddProvider1(): void
    {
        $source = new StaticArray(["KEY_1" => "value-2",]);
        $this->env->addSource($source);
        $env = new XRay($this->env);
        self::assertCount(2, $env->sources);
        self::assertSame($source, $env->sources[0]);
    }

    /** Ensure get() reads a value that exists in the environment. */
    public function testGet1(): void
    {
        self::assertEquals("value-1", $this->env->get("KEY_1"));
    }

    /** Ensure get() returns an empty string for a key that does not exist in the environment. */
    public function testGet2(): void
    {
        self::assertEquals("", $this->env->get("KEY_2"));
    }

    /** Ensure get() returns the value from the provider with the highest precedence. */
    public function testGet3(): void
    {
        $this->env->addSource(new StaticArray(["KEY_1" => "value-2",]));
        self::assertEquals("value-2", $this->env->get("KEY_1"));
    }

    /** Ensure get() uses providers with lower precedence when key missing in higher-precedence providers. */
    public function testGet4(): void
    {
        $this->env->addSource(new StaticArray(["KEY_2" => "value-2",]));
        self::assertEquals("value-1", $this->env->get("KEY_1"));
    }

    /** Ensure get() logs a warning if a provider throws. */
    public function testGet5(): void
    {
        $provider = new class([]) extends StaticArray
        {
            public function has(string $name): bool
            {
                throw new EnvironmentException("Unable to check for variable {$name}.");
            }
        };

        $app = Mockery::mock(Application::class);
        $log = Mockery::mock(Logger::class);

        $log->shouldReceive("warning")
            ->once()
            ->with(Mockery::on(
                fn (string $message):  bool => (bool) preg_match("/^Environment exception querying environment source of type .*: Unable to check for variable KEY_1\\.\$/", $message)
            ));

        $app->shouldReceive("get")
            ->with(Logger::class)
            ->once()
            ->andReturn($log);

        $this->mockMethod(Application::class, "instance", $app);
        $this->env->addSource($provider);
        self::assertEquals("value-1", $this->env->get("KEY_1"));
    }

    /** Ensure has() returns true for a value that exists in the environment. */
    public function testHas1(): void
    {
        self::assertTrue($this->env->has("KEY_1"));
    }

    /** Ensure has() retruns false for a key that does not exist in the environment. */
    public function testHas2()
    {
        self::assertFalse($this->env->has("KEY_2"));
    }

    /** Ensure has() checks all providers if necessary. */
    public function testHas3(): void
    {
        $this->env->addSource(new StaticArray(["KEY_2" => "value-2",]));
        self::assertTrue($this->env->has("KEY_1"));
        self::assertTrue($this->env->has("KEY_2"));
    }

    /** Ensure names() returns the names of all the variables in a single-source environment. */
    public function testNames1(): void
    {
        self::assertEqualsCanonicalizing(array_keys(self::TestEnvironment), $this->env->names());
    }

    /** Ensure names() returns the names of all the variables from all sources. */
    public function testNames2(): void
    {
        $this->env->addSource(new StaticArray(["KEY_2" => "value-2",]));
        self::assertEqualsCanonicalizing([... array_keys(self::TestEnvironment), "KEY_2",], $this->env->names());
    }

    /** Ensure names() skips names it's already encountered (regression coverage). */
    public function testNames3(): void
    {
        $this->env->addSource(new StaticArray(["KEY_1" => "value-1A",]));
        self::assertEqualsCanonicalizing(array_keys(self::TestEnvironment), $this->env->names());
    }

    /** Ensure all() returns all the variables in a single-source environment. */
    public function testAll1(): void
    {
        self::assertEquals(self::TestEnvironment, $this->env->all());
    }

    /** Ensure all() returns all the variables from all sources. */
    public function testAll2(): void
    {
        $this->env->addSource(new StaticArray(["KEY_2" => "value-2",]));
        $expected = self::TestEnvironment;
        $expected["KEY_2"] = "value-2";
        self::assertEquals($expected, $this->env->all());
    }

    /** Ensure all() applies the variable precedence rules. */
    public function testAll3(): void
    {
        // overwrites KEY_1 and adds KEY_2; KEY_3 should still come from the original source
        $this->env->addSource(new StaticArray(["KEY_1" => "value-1A", "KEY_2" => "value-2",]));
        $expected = self::TestEnvironment;
        $expected["KEY_1"] = "value-1A";
        $expected["KEY_2"] = "value-2";
        self::assertEquals($expected, $this->env->all());
    }
}
