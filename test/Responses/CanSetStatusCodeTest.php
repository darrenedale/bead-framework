<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\CanSetStatusCode;
use BeadTests\Framework\TestCase;

final class CanSetStatusCodeTest extends TestCase
{
    /** Helper to create a new instance of a class that imports the trait under test. */
    private static function createInstance(): object
    {
        return new class
        {
            use CanSetStatusCode;

            public function __construct()
            {
                $this->m_statusCode = 200;
            }
        };
    }


    /** Ensure we can fetch the status code. */
    public function testStatusCode1(): void
    {
        self::assertEquals(200, self::createInstance()->statusCode());
    }

    /** Ensure we can set the status code. */
    public function testSetStatusCode1(): void
    {
        $instance = self::createInstance();
        $instance->setStatusCode(400);
        self::assertEquals(400, $instance->statusCode());
    }
}
