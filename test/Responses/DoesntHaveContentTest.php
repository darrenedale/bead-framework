<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\DoesntHaveContent;
use BeadTests\Framework\TestCase;

class DoesntHaveContentTest extends TestCase
{
    /** Helper to create a new instance of a class that imports the trait under test. */
    private static function createInstance(): object
    {
        return new class
        {
            use DoesntHaveContent;
        };
    }

    /** Ensure the content-type is an empty string. */
    public function testContentType1(): void
    {
        self::assertEquals("", self::createInstance()->contentType());
    }

    /** Ensure the content is an empty string. */
    public function testContent1(): void
    {
        self::assertEquals("", self::createInstance()->content());
    }
}
