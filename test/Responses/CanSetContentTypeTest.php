<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\CanSetContentType;
use BeadTests\Framework\TestCase;

final class CanSetContentTypeTest extends TestCase
{
    /** Helper to create a new instance of a class that imports the trait under test. */
    private static function createInstance(): object
    {
        return new class
        {
            use CanSetContentType;
        };
    }

    /** Ensure we can fetch the content-type. */
    public function testContentType1(): void
    {
        self::assertEquals("application/octet-stream", self::createInstance()->contentType());
    }

    /** Ensure we can set the content-type. */
    public function testSetContentType1(): void
    {
        $instance = self::createInstance();
        $instance->setContentType("application/json");
        self::assertEquals("application/json", $instance->contentType());
    }
}
