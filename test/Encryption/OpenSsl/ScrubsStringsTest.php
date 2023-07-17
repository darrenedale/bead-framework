<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Encryption\OpenSsl\ScrubsStrings;
use BeadTests\Framework\TestCase;

class ScrubsStringsTest extends TestCase
{
    /** @var ScrubsStrings */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use ScrubsStrings;

            public static function callScrubString(string &$str): void
            {
                self::scrubString($str);
            }
        };
    }

    public function testScrubString(): void
    {
        $str = "something";
        $this->instance->callScrubString($str);
        self::assertEquals(9, strlen($str));
        self::assertEquals("\0\0\0\0\0\0\0\0\0", $str);
    }
}
