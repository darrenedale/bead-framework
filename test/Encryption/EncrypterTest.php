<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Encryption\Encrypter;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use Bead\Exceptions\EncryptionException;
use SodiumException;

class EncrypterTest extends TestCase
{
    private const EncryptionKey = "-some-insecure-key-insecure-some";

    private const RawData = "the-data";

    private const ArrayRawData = ["the-data", "more-data"];

    private Encrypter $encrypter;

    public function setUp(): void
    {
        $this->encrypter = new Encrypter(self::EncryptionKey);
    }

    public function testConstructor(): void
	{
		$crypter = new Encrypter(self::EncryptionKey);
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
		new Encrypter($key);
	}

    public function testEncrypt(): void
    {
        self::mockFunction("random_bytes", "000011112222333344445555");
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->encrypter->encrypt(self::RawData));
    }

    public function testEncryptDoesntAutoSerializeString(): void
    {
        self::mockFunction("random_bytes", "000011112222333344445555");
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->encrypter->encrypt(self::RawData, SerializationMode::Auto));
    }

    public function testEncryptAutoSerializes(): void
    {
        self::mockFunction("random_bytes", "000011112222333344445555");
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WeAelQVLV8IHIWXYsUXxm95ZfdnvELEzY1npRj1hPCebFKtX+vA11J5LQTo9qBPjRhbCQJe+XTtruh9E4rY=", $this->encrypter->encrypt(self::ArrayRawData, SerializationMode::Auto));
    }

    public function testEncryptForceSerializes(): void
    {
        self::mockFunction("random_bytes", "000011112222333344445555");
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=", $this->encrypter->encrypt(self::RawData, SerializationMode::On));
    }

    public function testEncryptForceNotToSerialize(): void
    {
        self::mockFunction("random_bytes", "000011112222333344445555");
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->encrypter->encrypt(self::RawData, SerializationMode::Off));
    }

    public function testEncryptInvalidSerializationModeThrows(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid serialization mode");
        $this->encrypter->encrypt(self::RawData, -1);
    }

    public function testEncryptThrowsWhenSodiumThrows(): void
    {
        $this->mockFunction('sodium_crypto_secretbox', function() {
            throw new SodiumException('The Sodium Exception');
        });

        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Exception encrypting data: The Sodium Exception");
        $this->encrypter->encrypt(self::RawData);
    }
}
