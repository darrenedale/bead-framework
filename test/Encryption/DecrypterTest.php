<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Encryption\Decrypter;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Exception;

// TODO ensure decrypt() throws with invalid base64
// TODO ensure decrypt() throws with truncated data
// TODO ensure decrypt() throws with invalid serialized flag
// TODO ensure decrypt() throws when sodium throws
// TODO ensure decrypt() throws when sodium returns false
// TODO ensure decrypt() throws when unserialize fails
// TODO ensure decrypt() handles serialization of false as special case
class DecrypterTest extends TestCase
{
    private const EncryptionKey = '-some-insecure-key-insecure-some';

    private const RawData = 'the-data';

    private const ArrayRawData = ['the-data', 'more-data'];

    private Decrypter $decrypter;

    public function setUp(): void
    {
        $this->decrypter = new Decrypter(self::EncryptionKey);
    }

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
		self::expectException(Exception::class);
		self::expectExceptionMessage('Invalid encryption key');
		new Decrypter($key);
	}

    public function dataForTestDecrypt(): iterable
    {
        yield "serialized string" => ["MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=", self::RawData];
        yield "serialized array" => ["MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WeAelQVLV8IHIWXYsUXxm95ZfdnvELEzY1npRj1hPCebFKtX+vA11J5LQTo9qBPjRhbCQJe+XTtruh9E4rY=", self::ArrayRawData];
        yield "unserialized string" => ["MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", self::RawData];
    }

    /** @dataProvider dataForTestDecrypt */
    public function testDecrypt(string $encrypted, mixed $expected): void
    {
        self::assertEquals($expected, $this->decrypter->decrypt($encrypted));
    }
}
