<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Encryption\Sodium\Decrypter;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class DecrypterTest extends TestCase
{
    private const EncryptionKey = '-some-insecure-key-insecure-some';

    public function testConstructor(): void
	{
		$crypter = new Decrypter(self::EncryptionKey);
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
		self::expectExceptionMessage('Invalid encryption key');
		new Decrypter($key);
	}
}

