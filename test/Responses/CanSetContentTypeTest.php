<?php

namespace BeadTests\Responses;

use Bead\Contracts\Response;
use Bead\Responses\CanSetContentType;
use PHPUnit\Framework\TestCase;

final class CanSetContentTypeTest extends TestCase
{


    /** Helper to create a new instance of a class that imports the trait under test. */
    private function createInstance(): mixed
    {
        return new class
        {
            use CanSetContentType;
        };
    }


    /** Ensure we can fetch the content-type. */
    public function testStatusCode(): void
    {
        $this->assertEquals("application/octet-stream", $this->createInstance()->contentType());
    }

    /** Ensure we can set the content-type. */
    public function testSetStatusCode(): void
    {
        $instance = $this->createInstance();
        $instance->setContentType("application/json");
        $this->assertEquals("application/json", $instance->contentType());
    }
}
