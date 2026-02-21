<?php

namespace BeadTests\Util;

use Bead\Util\ConstantTime;
use Bead\Util\ScopeGuard;
use BeadTests\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(ConstantTime::class)]
class ConstantTimeTest extends TestCase
{
    /** Ensure the constructor throws if high-resolution time is not available. */
    public function testConstructor1(): void
    {
        $this->mockFunction("hrtime", false);

        // PHPUnit uses hrtime() for telemetry, so we ensure the mock is removed as soon as this test exits so that we
        // don't break PHPUnit's telemetry
        $guard = new ScopeGuard(fn () => $this->removeFunctionMock("hrtime"));

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage("High resolution time is not available");
        new ConstantTime(1000);
    }

    /** Ensure the ConstantTime sleeps on destruction. */
    public function testDestructor1(): void
    {
        $usleepCalled = false;

        $this->mockFunction("usleep", static function (int $duration) use (&$usleepCalled): void {
            TestCase::assertGreaterThan(900, $duration);
            TestCase::assertLessThan(1000, $duration);
            $usleepCalled = true;
        });

        $actual = new ConstantTime(1000);
        $actual->__destruct();
        self::assertTrue($usleepCalled);
    }

    /** Ensure the ConstantTime doesn't sleep on destruction if join() was called. */
    public function testDestructor2(): void
    {
        $usleepCalled = 0;

        $this->mockFunction("usleep", static function (int $duration) use (&$usleepCalled): void {
            if (0 === $usleepCalled) {
                TestCase::assertGreaterThan(900, $duration);
                TestCase::assertLessThan(1000, $duration);
            }

            ++$usleepCalled;
        });

        $actual = new ConstantTime(1000);
        $actual->join();
        $actual->__destruct();
        self::assertSame(1, $usleepCalled);
    }

    /** Ensure join() sleeps for a suitable duration. */
    public function testJoin1(): void
    {
        $usleepCalled = false;

        $this->mockFunction("usleep", static function (int $duration) use (&$usleepCalled): void {
            // give join() a 0.25s window to run - should be ample to account for differences in test execution time
            TestCase::assertGreaterThanOrEqual(750, $duration);
            TestCase::assertLessThanOrEqual(1000, $duration);
            $usleepCalled = true;
        });

        $actual = new ConstantTime(1000);
        $actual->join();
        self::assertTrue($usleepCalled);
    }

    /** Ensure join() only sleeps once. */
    public function testJoin2(): void
    {
        $usleepCalled = 0;

        $this->mockFunction("usleep", static function (int $duration) use (&$usleepCalled): void {
            if (0 === $usleepCalled) {
                TestCase::assertGreaterThan(900, $duration);
                TestCase::assertLessThan(1000, $duration);
            }

            ++$usleepCalled;
        });

        $actual = new ConstantTime(1000);
        $actual->join();
        $actual->join();
        self::assertSame(1, $usleepCalled);
    }

    /** Ensure cancel prevents the ConstantTime pausing in join(). */
    public function testCancel1(): void
    {
        $usleepCalled = false;

        $this->mockFunction("usleep", static function (int $duration) use (&$usleepCalled): void {
            $usleepCalled = true;
        });

        $actual = new ConstantTime(1000);
        $actual->cancel();
        $actual->join();
        self::assertFalse($usleepCalled);
    }
}
