<?php

declare(strict_types=1);

namespace Encryption;

use Bead\Encryption\ScrubsStrings;
use BeadTests\Framework\TestCase;

class ScrubsStringsTest extends TestCase
{
    /** @var ScrubsStrings */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class
        {
            use ScrubsStrings;

            public static function callScrubString(string & $str): void
            {
                self::scrubString($str);
            }
        };
    }

    /** Ensure scrubString() calls rand() to generate bytes to overwrite the string's content. */
    public function testScrubString1(): void
    {
        $sequence = 0;

        $this->mockFunction(
            "rand",
            function (int $low, int $high) use (&$sequence): int {
                TestCase::assertEquals(0, $low);
                TestCase::assertEquals(255, $high);
                return $sequence++;
            }
        );

        $str = "something";
        $this->instance->callScrubString($str);
        self::assertEquals(9, strlen($str));
        self::assertEquals(9, $sequence);
        self::assertEquals("\x08\x07\x06\x05\x04\x03\x02\x01\x00", $str);
    }
}
