<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Encryption\Sodium\Decrypter;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class DecrypterTest extends TestCase
{
    use ProvidesInvalidKeys;

    private const EncryptionKey = "-some-insecure-key-insecure-some";

    /** Ensure we can construct a Decrypter with a valid key. */
    public function testConstructor1(): void
    {
        $crypter = new Decrypter(self::EncryptionKey);
        self::assertEquals(self::EncryptionKey, (new XRay($crypter))->key());
    }

    public static function dataForTestConstructor2(): iterable
    {
        yield from self::invalidKeys();
    }

    /**
     * Ensure constructor throws with invalid keys.
     *
     * @dataProvider dataForTestConstructor2
     */
    public function testConstructor2(string $key): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encryption key");
        new Decrypter($key);
    }
}
