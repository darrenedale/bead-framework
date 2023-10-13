<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Encryption\Sodium\Decrypts;
use Bead\Exceptions\EncryptionException;
use SodiumException;

class DecryptsTest extends \BeadTests\Framework\TestCase
{
    private const RawData = "the-data";

    private const ArrayRawData = ["the-data", "more-data"];

    /** @var Decrypts */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use Decrypts;

            public function key(): string
            {
                return "-some-insecure-key-insecure-some";
            }
        };
    }

    public static function dataForTestDecrypt1(): iterable
    {
        yield "serialized string" => ["MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=", self::RawData];
        yield "serialized array" => ["MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WeAelQVLV8IHIWXYsUXxm95ZfdnvELEzY1npRj1hPCebFKtX+vA11J5LQTo9qBPjRhbCQJe+XTtruh9E4rY=", self::ArrayRawData];
        yield "unserialized string" => ["MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", self::RawData];
    }

    /**
	 * Ensure decrypt() successfully decrypts data.
	 *
	 * @dataProvider dataForTestDecrypt1
	 */
    public function testDecrypt1(string $encrypted, mixed $expected): void
    {
        self::assertEquals($expected, $this->instance->decrypt($encrypted));
    }

	/** Ensure decrypt() throws with invalid base64 data. */
    public function testDecrypt2(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessageMatches("/^Invalid encrypted data:/");
        // absence of = padding should cause decoding to fail because sodium b64 decoding is strictly conformant
        $this->instance->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg");
    }

	/** Ensure decrypt() throws with truncated data. */
    public function testDecrypt3(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (truncated)");
        $this->instance->decrypt("MDAwMDExMTEy");
    }

	/** Ensure decrypt() throws when the serialization flag in the encrypted data is invalid. */
    public function testDecrypt4(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (bad serialization flag)");
        // this base64 has S where the serialization flag should be
        $this->instance->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1UwRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

	/** Ensure decrypt throws an EncryptionException when Sodium throws. */
    public function testDecrypt5(): void
    {
        $this->mockFunction("sodium_crypto_secretbox_open", function() {
            throw new SodiumException("The Sodium Exception");
        });

        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Exception decrypting data: The Sodium Exception");
        $this->instance->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

	/** Ensure decrypt throws when Sodium fails. */
    public function testDecrypt6(): void
    {
        $this->mockFunction("sodium_crypto_secretbox_open", fn(): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Unable to decrypt data");
        $this->instance->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

	/** Ensure decrypt throws when unserialization fails. */
    public function testDecrypt7(): void
    {
        $this->mockFunction("unserialize", fn(): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("The decrypted data could not be unserialized");
        $this->instance->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=");
    }

    /**
	 * Ensure decrypting the encrypted value `false` works.
	 *
	 * Since serialize() returns false both when it fails and when it successfully unserializes the serialization of
     * false, we need a test to prove unserializing false works as expected.
     */
    public function testDecrypt8(): void
    {
        self::assertFalse($this->instance->decrypt("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WRAMN0yXiip7bqD7ICAwK1Zafdvu"));
    }
}
