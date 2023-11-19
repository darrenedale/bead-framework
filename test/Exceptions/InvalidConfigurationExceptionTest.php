<?php

declare(strict_types=1);

namespace BeadTests\Exceptions;

use Bead\Exceptions\InvalidConfigurationException;
use BeadTests\Framework\TestCase;
use RuntimeException;

final class InvalidConfigurationExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    /** Ensure the invalid config key can be set in the exception constructor. */
    public function testConstructor1(): void
    {
        $exception = new InvalidConfigurationException("app.language");
        self::assertEquals("app.language", $exception->getKey());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set an exception code in the constructor. */
    public function testConstructor2(): void
    {
        $exception = new InvalidConfigurationException("app.language", code: 42);
        self::assertEquals("app.language", $exception->getKey());
        self::assertCode(42, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set a message in the constructor. */
    public function testConstructor3(): void
    {
        $exception = new InvalidConfigurationException("app.language", message: "The meaning of life.");
        self::assertEquals("app.language", $exception->getKey());
        self::assertCode(0, $exception);
        self::assertMessage("The meaning of life.", $exception);
        self::assertPrevious(null, $exception);
    }

    /** Ensure we can set a previous exception in the constructor. */
    public function testConstructor4(): void
    {
        $previous = new RuntimeException();
        $exception = new InvalidConfigurationException("app.language", previous: $previous);
        self::assertEquals("app.language", $exception->getKey());
        self::assertCode(0, $exception);
        self::assertMessage("", $exception);
        self::assertPrevious($previous, $exception);
    }
}
