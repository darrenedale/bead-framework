<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Encryption\SerializationMode;
use Bead\Encryption\Sodium\Crypter;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use SodiumException;

class CrypterTest extends TestCase
{
    use ProvidesInvalidKeys;

    private const EncryptionKey = "-some-insecure-key-insecure-some";

    /** Ensure we can construct a Crypter with a valid key. */
    public function testConstructor1(): void
    {
        $crypter = new Crypter(self::EncryptionKey);
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
		new Crypter($key);
	}
}
