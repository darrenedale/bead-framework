<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Encryption\Crypter;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Exception;

class CrypterTest extends TestCase
{
    private const EncryptionKey = '-some-insecure-key-insecure-some';

    private const RawData = 'the-data';

    private const ArrayRawData = ['the-data', 'more-data'];

    private Crypter $crypter;

    public function setUp(): void
    {
        $this->crypter = new Crypter(self::EncryptionKey);
    }

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
		self::expectException(Exception::class);
		self::expectExceptionMessage('Invalid encryption key');
		new Crypter($key);
	}

    public function testEncrypt(): void
    {
        self::mockFunction('random_bytes', '000011112222333344445555');
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->crypter->encrypt(self::RawData));
    }

    public function testEncryptDoesntAutoSerializeString(): void
    {
        self::mockFunction('random_bytes', '000011112222333344445555');
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->crypter->encrypt(self::RawData, SerializationMode::Auto));
    }

    public function testEncryptAutoSerializes(): void
    {
        self::mockFunction('random_bytes', '000011112222333344445555');
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WeAelQVLV8IHIWXYsUXxm95ZfdnvELEzY1npRj1hPCebFKtX+vA11J5LQTo9qBPjRhbCQJe+XTtruh9E4rY=", $this->crypter->encrypt(self::ArrayRawData, SerializationMode::Auto));
    }

    public function testEncryptForceSerializes(): void
    {
        self::mockFunction('random_bytes', '000011112222333344445555');
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=", $this->crypter->encrypt(self::RawData, SerializationMode::On));
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
        self::assertEquals($expected, $this->crypter->decrypt($encrypted));
    }
}
