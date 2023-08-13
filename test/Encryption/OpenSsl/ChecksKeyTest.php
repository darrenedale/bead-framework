<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

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

    public function dataForTestCheckKey(): iterable
    {
        yield "empty" => ["", false];

        for ($chars = 1; $chars < 27; ++$chars) {
            yield "{$chars} chars" => [str_repeat("X", $chars), 24 <= $chars];
        }
    }

    /** @dataProvider dataForTestCheckKey */
    public function testCheckKey(string $key, bool $passes): void
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
