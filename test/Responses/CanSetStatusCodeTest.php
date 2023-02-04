<?php

namespace BeadTests\Responses;

use Bead\Contracts\Response;
use Bead\Responses\CanSetStatusCode;
use PHPUnit\Framework\TestCase;

final class CanSetStatusCodeTest extends TestCase
{


    /** Helper to create a new instance of a class that imports the trait under test. */
    private function createInstance(): mixed
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
    public function testStatusCode(): void
    {
        $this->assertEquals(200, $this->createInstance()->statusCode());
    }

    /** Ensure we can set the status code. */
    public function testSetStatusCode(): void
    {
        $instance = $this->createInstance();
        $instance->setStatusCode(400);
        $this->assertEquals(400, $instance->statusCode());
    }
}
