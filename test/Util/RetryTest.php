<?php

declare(strict_types=1);

namespace BeadTests\Util;

use BeadTests\Framework\TestCase;
use Bead\Util\Retry;
use InvalidArgumentException;
use TypeError;

class RetryTest extends TestCase
{
    /**
     * Helper to create a callable to test with.
     *
     * @return callable The test callable.
     */
    private static function createCallable(): callable
    {
        return function () {
        };
    }

    /**
     * Static function to use as an exit condition in tests.
     *
     * @param $value The value to test.
     *
     * @return bool Always `true`.
     */
    public static function staticExitFunction($value): bool
    {
        return true;
    }

    /**
     * Function to use as an exit condition in tests.
     *
     * @param $value The value to test.
     *
     * @return bool Always `true`.
     */
    public function exitFunction($value): bool
    {
        return true;
    }

    /**
     * Static function to use as a callable in tests.
     *
     * @return bool Always `null`.
     */
    public static function staticCallableToRetry()
    {
        return null;
    }

    /**
     * Function to use as a callable in tests.
     *
     * @return bool Always `null`.
     */
    public function callableToRetry()
    {
        return null;
    }

    /**
     * Ensure the constructor sets up the Retry object in the expected state.
     */
    public function testConstructor(): void
    {
        $fn = self::createCallable();
        $retry = new Retry($fn);
        self::assertSame($fn, $retry->retry());
        self::assertSame(1, $retry->maxRetries());
        self::assertNull($retry->exitCondition());
    }

    /**
     * Test data for testTimes().
     *
     * @return iterable The test data.
     */
    public function dataForTestTimes(): iterable
    {
        yield from [
            "typical5" => [5,],
            "extreme1" => [1,],
            "extremeIntMax" => [PHP_INT_MAX,],
            "invalid0" => [0, InvalidArgumentException::class,],
            "invalid-1" => [-1, InvalidArgumentException::class,],
            "invalidPhpIntMin" => [PHP_INT_MIN, InvalidArgumentException::class,],

            "invalidString" => ["5", TypeError::class],
            "invalidObject" => [
                new class
                {
                    private int $m_retry = 5;

                    public function retry(): int
                    {
                        return $this->m_retry;
                    }
                },
                TypeError::class,
            ],
            "invalidNull" => [null, TypeError::class],
            "invalidArray" => [[5,], TypeError::class],
            "invalidBoolean" => [[true,], TypeError::class],
        ];
    }

    /**
     * Ensure time() sets the max retry count and returns the same Retry instance.
     *
     * @dataProvider dataForTestTimes
     *
     * @param $times
     * @param string|null $exceptionClass
     */
    public function testTimes($times, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $retry = new Retry(self::createCallable());
        $actual = $retry->times($times);
        self::assertSame($retry, $actual);
        self::assertEquals($times, $actual->maxRetries());
    }

    /**
     * Test data for testUntil().
     *
     * @return iterable The test data.
     */
    public function dataForTestUntil(): iterable
    {
        yield from [
            "typicalLambda" => [5, fn (): bool => true,],
            "typicalStaticMethod" => [5, [self::class, "staticExitFunction"],],
            "typicalMethod" => [5, [$this, "exitFunction"],],
            "typicalInvokable" => [
                5,
                new class
                {
                    public function __invoke($value): bool
                    {
                        return true;
                    }
                },
            ],
            "invalidString" => [5, "standaloneExitFunction", TypeError::class,],
            "invalidArray" => [5, ["standaloneExitFunction",], TypeError::class,],
            "invalidObject" => [5, new class {
            }, TypeError::class,],
            "invalidInt" => [5, 42, TypeError::class,],
            "invalidFloat" => [5, 3.1415927, TypeError::class,],
            "invalidBool" => [5, true, TypeError::class,],
            "invalidNull" => [5, null, TypeError::class,],
        ];
    }

    /**
     * Ensure the until() method sets the exit condition and returns the same Retry instance.
     *
     * @dataProvider dataForTestUntil
     *
     * @param int $times
     * @param $predicate
     * @param string|null $exceptionClass
     *
     * @return void
     */
    public function testUntil(int $times, $predicate, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $retry = (new Retry(self::createCallable()))
            ->times($times);

        $actual = $retry->until($predicate);
        self::assertSame($retry, $actual);
        self::assertSame($predicate, $retry->exitCondition());
    }

    /**
     * Test data for invokeFixedTimes()
     *
     * @return iterable The test data.
     */
    public function dataForTestInvokeFixedTimes(): iterable
    {
        for ($times = 1; $times < 30; ++$times) {
            yield [$times,];
        }
    }

    /**
     * Ensure invoking with no exit condition retries the max number of times.
     *
     * @param int $times The max retries.
     *
     * @dataProvider dataForTestInvokeFixedTimes
     */
    public function testInvokeFixedTimes(int $times): void
    {
        $actualTimes = 0;

        $invokable = function () use (&$actualTimes): void {
            ++$actualTimes;
        };

        $retry = (new Retry($invokable))
            ->times($times);

        $retry();
        self::assertLessThanOrEqual($times, $retry->attemptsTaken());
        self::assertEquals($times, $actualTimes);
    }

    /**
     * Test data for testInvokeWithExitCondition.
     *
     * @return iterable The test data.
     */
    public function dataForTestInvokeWithExitCondition(): iterable
    {
        yield from [
            "typicalPassesFirstTime" => [
                function () {
                    return 6;
                },
                fn (int $result): bool => 6 === $result,
                5,
                1,
                true,
                6,
            ],
            "typicalPassesLastTime" => [
                function () {
                    static $ret = 0;
                    return ++$ret;
                },
                fn (int $result): bool => 5 === $result,
                5,
                5,
                true,
                5,
            ],
            "extremeOneAttemptMaxPasses" => [
                function () {
                    static $ret = 0;
                    return ++$ret;
                },
                fn (int $result): bool => 1 === $result,
                1,
                1,
                true,
                1,
            ],
            "typicalPassesAfterThreeOfFive" => [
                function () {
                    static $ret = 0;
                    $ret += 2;
                    return $ret;
                },
                fn (int $result): bool => 6 === $result,
                5,
                3,
                true,
                6,
            ],
            "typicalWouldPassWithMoreAttempts" => [
                function () {
                    static $ret = 0;
                    $ret += 2;
                    return $ret;
                },
                fn (int $result): bool => 6 === $result,
                2,
                2,
                false,
                null,
            ],
            "extremeCanNeverPass" => [
                function () {
                    return mt_rand(1, 100);
                },
                fn ($result): bool => "never this" === $result,
                5,
                5,
                false,
                null,
            ],
        ];
    }

    /**
     * Ensure invoking a Retry produces the expected results when an exit condition is present.
     *
     * @dataProvider dataForTestInvokeWithExitCondition
     *
     * @param callable $retryCode The callable to retry.
     * @param callable $exitCondition The exit condition for the retry loop.
     * @param int $maxTimes The maximum number of times to retry.
     * @param int $expectedTimes The number of times the code is expected to actually be retried.
     * @param bool $expectedSuccess Whether the retry is expected ultimately to succeed.
     * @param mixed $expectedResult The expected return value from invoking the Retry.
     */
    public function testInvokeWithExitCondition(callable $retryCode, callable $exitCondition, int $maxTimes, int $expectedTimes, bool $expectedSuccess, $expectedResult = null): void
    {
        $retry = (new Retry($retryCode))
            ->times($maxTimes)
            ->until($exitCondition);

        $result = $retry();
        self::assertEquals($expectedTimes, $retry->attemptsTaken());
        self::assertLessThanOrEqual($maxTimes, $retry->attemptsTaken());
        self::assertEquals($expectedSuccess, $retry->succeeded());
        self::assertEquals($expectedResult, $result);
    }

    /**
     * Data for testSetMaxRetries()
     *
     * @return iterable The test data.
     */
    public function dataForTestSetMaxRetries(): iterable
    {
        yield from $this->dataForTestMaxRetries();

        yield "invalidZero" => [0, InvalidArgumentException::class,];

        for ($retries = -1; $retries > -30; --$retries) {
            yield "invalid{$retries}" => [$retries, InvalidArgumentException::class,];
        }

        yield from [
            "invalidString" => ["5",TypeError::class,],
            "invalidArray" => [[5,], TypeError::class,],
            "invalidObject" => [
                new class {
                    public function __toString(): string
                    {
                        return "5";
                    }
                },
                TypeError::class,
            ],
            "invalidClosure" => [fn (): int => 5, TypeError::class,],
            "invalidFloat" => [3.1415927, TypeError::class,],
            "invalidBool" => [true, TypeError::class,],
            "invalidNull" => [null, TypeError::class,],
        ];
    }

    /**
     * Ensure setMaxRetries() sets the maximum and resets the internal outcome state.
     *
     * @dataProvider dataForTestSetMaxRetries
     *
     * @param mixed $retries The number of retries to test with.
     * @param string|null $exceptionClass The class of the exception expected, if any.
     */
    public function testSetMaxRetries($retries, ?string $exceptionClass = null): void
    {
        $retry = (new Retry(self::createCallable()));
        $retry();
        self::assertEquals(1, $retry->attemptsTaken());

        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $retry->setMaxRetries($retries);
        self::assertEquals($retries, $retry->maxRetries());
        self::assertNull($retry->attemptsTaken());
    }

    /**
     * Test data for testMaxRetries()
     *
     * @return iterable The test data.
     */
    public function dataForTestMaxRetries(): iterable
    {
        for ($retries = 1; $retries < 30; ++$retries) {
            yield "typical{$retries}" => [$retries,];
        }
    }

    /**
     * Ensure maxRetries() accessor returns the expected value.
     *
     * @dataProvider dataForTestMaxRetries
     *
     * @param int $retries The number of retries to test with.
     */
    public function testMaxRetries(int $retries): void
    {
        $retry = (new Retry(self::createCallable()))
            ->times($retries);

        self::assertEquals($retries, $retry->maxRetries());
    }

    /**
     * Test data for testSetRetry()
     *
     * @return iterable The test data.
     */
    public function dataForTestSetRetry(): iterable
    {
        yield from [
            "typicalClosure" => [self::createCallable(),],
            "typicalStaticMethod" => [[self::class, "staticCallableToRetry",],],
            "typicalMethod" => [[$this, "callableToRetry",],],
            "typicalInvokable" => [
                new class {
                    public function __invoke()
                    {
                        return null;
                    }
                },
            ],

            "invalidString" => ["5", TypeError::class,],
            "invalidArray" => [[5,], TypeError::class,],
            "invalidObject" => [
                new class {
                    public function __toString(): string
                    {
                        return "5";
                    }
                },
                TypeError::class,
            ],
            "invalidInt" => [42, TypeError::class,],
            "invalidFloat" => [3.1415927, TypeError::class,],
            "invalidBool" => [true, TypeError::class,],
            "invalidNull" => [null, TypeError::class,],
        ];
    }

    /**
     * Ensure setting the callable to retry works as expected and throws with invalid data.
     *
     * @dataProvider dataForTestSetRetry
     *
     * @param mixed $callable The data to test with.
     * @param string|null $exceptionClass The class of exception expected to be thrown, if any.
     */
    public function testSetRetry($callable, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $retry = new Retry(self::createCallable());
        $retry->setRetry($callable);
        self::assertSame($callable, $retry->retry());
    }

    /**
     * Test data for testRetry()
     *
     * @return iterable The test data.
     */
    public function dataForTestRetry(): iterable
    {
        yield from [
            "typicalClosure" => [self::createCallable(),],
            "typicalStaticMethod" => [[self::class, "staticCallableToRetry",],],
            "typicalMethod" => [[$this, "callableToRetry",],],
            "typicalInvokable" => [
                new class {
                    public function __invoke()
                    {
                        return null;
                    }
                },
            ],
        ];
    }

    /**
     * Ensure retry() accessor returns the expected value.
     *
     * @dataProvider dataForTestRetry
     */
    public function testRetry(callable $callable): void
    {
        $retry = new Retry($callable);
        self::assertSame($callable, $retry->retry());
    }

    /**
     * Test data for testSetExitCondition().
     *
     * @return iterable The test data.
     */
    public function dataForTestSetExitCondition(): iterable
    {
        yield from [
            "typicalClosure" => [self::createCallable(),],
            "typicalStaticMethod" => [[self::class, "staticExitFunction",],],
            "typicalMethod" => [[$this, "exitFunction",],],
            "typicalInvokable" => [
                new class {
                    public function __invoke()
                    {
                        return true;
                    }
                },
            ],
            "typicalNull" => [null,],

            "invalidString" => ["5", TypeError::class,],
            "invalidArray" => [[5,], TypeError::class,],
            "invalidObject" => [
                new class {
                    public function __toString(): string
                    {
                        return "5";
                    }
                },
                TypeError::class,
            ],
            "invalidInt" => [42, TypeError::class,],
            "invalidFloat" => [3.1415927, TypeError::class,],
            "invalidBool" => [true, TypeError::class,],
        ];
    }

    /**
     * Ensure the exit condition is set correctly, and throws with invalid data.
     *
     * @dataProvider dataForTestSetExitCondition
     */
    public function testSetExitCondition($exitCondition, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $retry = new Retry(self::createCallable());
        $retry->setExitCondition($exitCondition);
        self::assertSame($exitCondition, $retry->exitCondition());
    }

    /**
     * Test data for testExitCondition().
     *
     * @return iterable The test data.
     */
    public function dataForTestExitCondition(): iterable
    {
        yield from [
            "typicalClosure" => [self::createCallable(),],
            "typicalStaticMethod" => [[self::class, "staticExitFunction",],],
            "typicalMethod" => [[$this, "exitFunction",],],
            "typicalInvokable" => [
                new class {
                    public function __invoke()
                    {
                        return true;
                    }
                },
            ],
        ];
    }

    /**
     * @dataProvider dataForTestExitCondition
     */
    public function testExitCondition(callable $exitCondition): void
    {
        $retry = (new Retry(self::createCallable()))
            ->until($exitCondition);
        self::assertSame($exitCondition, $retry->exitCondition());
    }

    /**
     * Ensure attemptsTaken() returns the expected results:
     * - ensure it returns max when retry doesn't have an exit condition
     * - ensure it gets reset when max retries is changed
     * - ensure it gets reset when callable is changed
     * - ensure it gets reset when exit condition is changed
     * - ensure it returns the correct number of retries when an exit condition is present
     */
    public function testAttemptsTaken(): void
    {
        // ensure it returns null on initialisation
        $retry = new Retry(self::createCallable());
        self::assertNull($retry->attemptsTaken());

        // ensure it returns max when retry doesn't have an exit condition
        $retry->times(1);
        $retry();
        self::assertEquals(1, $retry->attemptsTaken());

        // ensure it gets reset when max changed
        $retry->times(2);
        self::assertNull($retry->attemptsTaken());
        $retry();
        self::assertEquals(2, $retry->attemptsTaken());

        // ensure it gets reset when callable changed
        $retry->setRetry(self::createCallable());
        self::assertNull($retry->attemptsTaken());
        $retry();
        self::assertEquals(2, $retry->attemptsTaken());

        // ensure it gets reset when exit condition changed
        $retry->setExitCondition(fn ($result): bool => true);
        self::assertNull($retry->attemptsTaken());

        // ensure it returns the correct value when the exit condition is in effect
        $retry();
        self::assertEquals(1, $retry->attemptsTaken());
    }

    public function testSucceeded(): void
    {
        // ensure it returns false on initialisation
        $retry = new Retry(self::createCallable());
        self::assertFalse($retry->succeeded());

        // ensure it returns true when exit condition passes
        $retry->setExitCondition(fn ($result): bool => true);
        $retry();
        self::assertTrue($retry->succeeded());

        // ensure it gets reset when max changed
        $retry->times(2);
        self::assertFalse($retry->succeeded());

        // ensure it returns true when exit condition passes with greater max
        $retry();
        self::assertEquals(1, $retry->attemptsTaken());
        self::assertTrue($retry->succeeded());

        // ensure it gets reset when callable changed
        $retry->setRetry(self::createCallable());
        self::assertFalse($retry->succeeded());

        // ensure it gets reset when exit condition changed
        $retry->setExitCondition(function ($result): bool {
            static $ret = false;

            if (!$ret) {
                $ret = true;
                return false;
            }

            return $ret;
        });

        self::assertFalse($retry->succeeded());

        // ensure it returns the correct value when the exit condition returns true on a later attempt
        $retry();
        self::assertEquals(2, $retry->attemptsTaken());
        self::assertTrue($retry->succeeded());
    }
}
