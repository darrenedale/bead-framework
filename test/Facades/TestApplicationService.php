<?php

declare(strict_types=1);

namespace BeadTests\Facades;

class TestApplicationService
{
    public const ExpectedReturnValue = "the test method return value";

    public function doSomething(): string
    {
        return self::ExpectedReturnValue;
    }
}
