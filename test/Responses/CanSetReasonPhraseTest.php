<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\CanSetReasonPhrase;
use BeadTests\Framework\TestCase;

final class CanSetReasonPhraseTest extends TestCase
{
    /** Create an anonymous object that imports the trait under test. */
    private static function createInstance(): object
    {
        return new class
        {
            use CanSetReasonPhrase;

            public function __construct()
            {
                $this->m_reasonPhrase = "OK";
            }
        };
    }


    /** Ensure we can fetch the reason phrase. */
    public function testReasonPhrase1(): void
    {
        self::assertEquals("OK", self::createInstance()->reasonPhrase());
    }

    /** Ensure we can set the reason phrase. */
    public function testSetReasonPhrase1(): void
    {
        $instance = self::createInstance();
        $instance->setReasonPhrase("Not OK");
        self::assertEquals("Not OK", $instance->reasonPhrase());
    }
}
