<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Encryption\Decrypter;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use SodiumException;

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
		self::expectException(EncryptionException::class);
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

    public function testDecryptThrowsWithInvalidBase64(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessageMatches("/^Invalid encrypted data:/");
        // absence of = padding should cause decoding to fail because sodium b64 decoding is strictly conformant
        $this->decrypter->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg");
    }

    public function testDecryptThrowsWithTruncatedData(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (truncated)");
        // this base64 has S where the serialization flag should be
        $this->decrypter->decrypt("MDAwMDExMTEy");
    }

    public function testDecryptThrowsWithBadSerializedFlag(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (bad serialization flag)");
        // this base64 has S where the serialization flag should be
        $this->decrypter->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1UwRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

    public function testDecryptThrowsWhenSodiumThrows(): void
    {
        $this->mockFunction('sodium_crypto_secretbox_open', function() {
            throw new SodiumException('The Sodium Exception');
        });

        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Exception decrypting data: The Sodium Exception");
        $this->decrypter->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

    public function testDecryptThrowsWhenSodiumFails(): void
    {
        $this->mockFunction('sodium_crypto_secretbox_open', fn(): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Unable to decrypt data");
        $this->decrypter->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

    public function testDecryptThrowsWhenUnserializeFails(): void
    {
        $this->mockFunction('unserialize', fn(): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("The decrypted data could not be unserialized");
        $this->decrypter->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

    /**
     * Since serialize() returns false both when it fails and when it successfully unserializes the serialization of
     * false, we need a test to prove unserializing false works as expected.
     */
    public function testDecryptHandlesSerializedFalse(): void
    {
        self::assertFalse($this->decrypter->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WRAMN0yXiip7bqD7ICAwK1Zafdvu"));
    }
}

