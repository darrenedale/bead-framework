<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Encryption\OpenSsl\ChecksKey;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\StaticXRay;
use BeadTests\Framework\TestCase;

class ChecksKeyTest extends TestCase
{
    /** @var ChecksKey */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use ChecksKey;
        };
    }

    /** Ensure the minimum key length is the expected value. */
    public function testMinimumKeyLength1(): void
    {
        self::assertEquals(24, (new StaticXRay($this->instance::class))->minimumKeyLength());
    }

    public static function dataForTestCheckKey1(): iterable
    {
        yield "empty" => ["", false];

        for ($chars = 1; $chars < 27; ++$chars) {
            yield "{$chars} chars" => [str_repeat("X", $chars), 24 <= $chars];
        }
    }

    /**
     * Ensure checkKey() provides the expected results.
     *
     * @dataProvider dataForTestCheckKey1
     */
    public function testCheckKey1(string $key, bool $passes): void
    {
        if (!$passes) {
            self::expectException(EncryptionException::class);
            self::expectExceptionMessage("Invalid encryption key");
        } else {
            // in this scenario, if the method doesn't throw the test passes
            self::markTestAsExternallyVerified();
        }

        (new StaticXRay(get_class($this->instance)))->checkKey($key);
    }
}
