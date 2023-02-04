<?php

namespace BeadTests\Exceptions;

use Throwable;

trait AssertsCommonExceptionProperties
{
    abstract public static function assertEquals(mixed $expected, mixed $actual, string $message = ""): void;

    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ""): void;

    abstract public static function assertMatchesRegularExpression(string $expected, string $actual, string $message = ""): void;

    private static function assertMessage(Throwable $throwable, string $message): void
    {
        self::assertEquals($message, $throwable->getMessage());
    }

    private static function assertMessageMatches(Throwable $throwable, string $pattern): void
    {
        self::assertMatchesRegularExpression($message, $throwable->getMessage());
    }

    private static function assertCode(Throwable $throwable, int $code): void
    {
        self::assertEquals($code, $throwable->getCode());
    }

    private static function assertPrevious(Throwable $throwable, ?Throwable $previous): void
    {
        self::assertSame($previous, $throwable->getPrevious());
    }
}
