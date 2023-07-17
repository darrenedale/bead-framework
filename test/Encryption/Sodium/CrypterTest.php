<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Encryption\Sodium\Crypter;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use SodiumException;

class CrypterTest extends TestCase
{
    private const EncryptionKey = "-some-insecure-key-insecure-some";

    public function testConstructor(): void
	{
		$crypter = new Crypter(self::EncryptionKey);
		self::assertEquals(self::EncryptionKey, (new XRay($crypter))->key());
	}

    public function dataForTestConstructorThrows(): iterable
    {
        yield "empty" => [""];
        yield "marginally too short" => ["some-insecure-key-insecure-some"];
        yield "marginally too long" => ["-some-insecure-key-insecure-some-"];
    }

    /** @dataProvider dataForTestConstructorThrows */
	public function testConstructorThrows(string $key): void
	{
		self::expectException(EncryptionException::class);
		self::expectExceptionMessage("Invalid encryption key");
		new Crypter($key);
	}
}
