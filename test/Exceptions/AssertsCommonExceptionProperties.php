<?php

namespace BeadTests\Exceptions;

use Throwable;

trait AssertsCommonExceptionProperties
{
    private function assertMessage(Throwable $throwable, string $message): void
    {
        $this->assertEquals($message, $throwable->getMessage());
    }

    private function assertMessageMatches(Throwable $throwable, string $pattern): void
    {
        $this->assertMatchesRegularExpression($message, $throwable->getMessage());
    }

    private function assertCode(Throwable $throwable, int $code): void
    {
        $this->assertEquals($code, $throwable->getCode());
    }

    private function assertPrevious(Throwable $throwable, ?Throwable $previous): void
    {
        $this->assertSame($previous, $throwable->getPrevious());
    }
}
