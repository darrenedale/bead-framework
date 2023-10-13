<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Encryption\Sodium\ChecksKey;
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

    public static function dataForTestCheckKey1(): iterable
    {
        yield "empty" => ["", false];

        for ($chars = 1; $chars < SODIUM_CRYPTO_SECRETBOX_KEYBYTES + 2; ++$chars) {
            yield "{$chars} chars" => [str_repeat("X", $chars), SODIUM_CRYPTO_SECRETBOX_KEYBYTES === $chars];
        }
    }

    /** @dataProvider dataForTestCheckKey1 */
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
