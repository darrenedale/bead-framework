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
        $this->assertEquals("random", $err->getDirection());
        $this->assertMessage($err, "");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithOrderByDirectionAndMessage(): void
    {
        $err = new InvalidOrderByDirectionException("random", "Message.");
        $this->assertEquals("random", $err->getDirection());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 0);
        $this->assertPrevious($err, null);
    }

    public function testWithOrderByDirectionMessageAndCode(): void
    {
        $err = new InvalidOrderByDirectionException("random", "Message.", 42);
        $this->assertEquals("random", $err->getDirection());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, null);
    }

    public function testWithOrderByDirectionMessageCodeAndPrevious(): void
    {
        $previous = new Exception();
        $err = new InvalidOrderByDirectionException("random", "Message.", 42, $previous);
        $this->assertEquals("random", $err->getDirection());
        $this->assertMessage($err, "Message.");
        $this->assertCode($err, 42);
        $this->assertPrevious($err, $previous);
    }
}
