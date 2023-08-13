<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Encryption\OpenSsl\Decrypts;
use Bead\Exceptions\EncryptionException;
use BeadTests\Framework\TestCase;

class DecryptsTest extends TestCase
{
    private const RawData = "the-data";

    private const ArrayRawData = ["the-data", "more-data"];

    /** @var Encrypts */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use Decrypts;

            public function algorithm(): string
            {
                return "des-ede-cbc";
            }

            public function key(): string
            {
                return "-some-insecure-key-insecure-some";
            }
        };
    }

    public function dataForTestDecrypt(): iterable
    {
        yield "serialized string" => ["MTExMTExMTFZNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09", self::RawData];
        yield "serialized array" => ["MTExMTExMTFZZlJGNXR5bCtjVGhqWkd2OXVRT1ZPV0tqMEVuSTR5K3d0VElHOWQ2M1EyenowOGZLQURKaDNiQytYNVBWOFZObw", self::ArrayRawData];
        yield "unserialized string" => ["MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09", self::RawData];
    }

    /** @dataProvider dataForTestDecrypt */
    public function testDecrypt(string $encrypted, mixed $expected): void
    {
        self::assertEquals($expected, $this->instance->decrypt($encrypted));
    }

    public function testDecryptThrowsWithInvalidBase64(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data");
        $this->instance->decrypt("MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09===");
    }

    public function testDecryptThrowsWithTruncatedData(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (truncated)");
        $this->instance->decrypt("MTExMTEx");
    }

    public function testDecryptThrowsWithBadSerializedFlag(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (bad serialization flag)");
        // this base64 has S where the serialization flag should be
        $this->instance->decrypt("MTExMTExMTFTNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09");
    }

    public function testDecryptThrowsWhenOpenSslFails(): void
    {
        $this->mockFunction("openssl_decrypt", fn(): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Unable to decrypt data");
        $this->instance->decrypt("MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09");
    }

    public function testDecryptThrowsWhenUnserializeFails(): void
    {
        $this->mockFunction("unserialize", fn(): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("The decrypted data could not be unserialized");
        $this->instance->decrypt("MTExMTExMTFZNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09");
    }

    /**
     * Since serialize() returns false both when it fails and when it successfully unserializes the serialization of
     * false, we need a test to prove unserializing false works as expected.
     */
    public function testDecryptHandlesSerializedFalse(): void
    {
        self::assertFalse($this->instance->decrypt("MTExMTExMTFZTUNKMTcxZ2ltTlk9"));
    }
}
