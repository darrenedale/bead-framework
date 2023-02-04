<?php

namespace Exceptions\Database;

use BeadTests\Framework\TestCase;
use Bead\Exceptions\Database\InvalidOrderByDirectionException;
use Exception;
use BeadTests\Exceptions\AssertsCommonExceptionProperties;

class InvalidOrderByDirectionExceptionTest extends TestCase
{
    use AssertsCommonExceptionProperties;

    public function testWithOrderByDirection(): void
    {
        $err = new InvalidOrderByDirectionException("random");
        self::assertEquals("random", $err->getDirection());
        self::assertMessage($err, "");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithOrderByDirectionAndMessage(): void
    {
        $err = new InvalidOrderByDirectionException("random", "Message.");
        self::assertEquals("random", $err->getDirection());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 0);
        self::assertPrevious($err, null);
    }

    public function testWithOrderByDirectionMessageAndCode(): void
    {
        $err = new InvalidOrderByDirectionException("random", "Message.", 42);
        self::assertEquals("random", $err->getDirection());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, null);
    }

    public function testWithOrderByDirectionMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidOrderByDirectionException("random", "Message.", 42, $previous);
        self::assertEquals("random", $err->getDirection());
        self::assertMessage($err, "Message.");
        self::assertCode($err, 42);
        self::assertPrevious($err, $previous);
    }
}
