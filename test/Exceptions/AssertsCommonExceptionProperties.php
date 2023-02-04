<?php

namespace BeadTests\Exceptions;

use Throwable;

/**
 * Trait for test cases that need to perform common assertions on Throwable objects.
 */
trait AssertsCommonExceptionProperties
{
    /** Constrain importing classes to those with an assertEquals() method. */
    abstract public static function assertEquals(mixed $expected, mixed $actual, string $message = ""): void;

    /** Constrain importing classes to those with an assertSame() method. */
    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ""): void;

    /** Constrain importing classes to those with an assertMatchesRegularExpression() method. */
    abstract public static function assertMatchesRegularExpression(string $expected, string $actual, string $message = ""): void;

    /** Assert that a Throwable has a given message. */
    private static function assertMessage(string $message, Throwable $throwable): void
    {
        self::assertEquals($message, $throwable->getMessage());
    }

    /** Assert that a Throwable has a message matching a regular expression. */
    private static function assertMessageMatches(string $pattern, Throwable $throwable): void
    {
        self::assertMatchesRegularExpression($message, $throwable->getMessage());
    }

    /** Assert that a Throwable has a given code. */
    private static function assertCode(int $code, Throwable $throwable): void
    {
        self::assertEquals($code, $throwable->getCode());
    }

    /** Assert that a Throwable's previous Throwable is a given instance (or null). */
    private static function assertPrevious(?Throwable $previous, Throwable $throwable): void
    {
        self::assertSame($previous, $throwable->getPrevious());
    }
}
