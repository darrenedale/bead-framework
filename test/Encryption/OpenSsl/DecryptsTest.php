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

    /** @var object $instance Instance of an anonymous class that utilises the Decrypts trait. */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class
        {
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

    public static function dataForTestDecrypt1(): iterable
    {
        yield "serialized string" => ["MTExMTExMTFZNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09", self::RawData];
        yield "serialized array" => ["MTExMTExMTFZZlJGNXR5bCtjVGhqWkd2OXVRT1ZPV0tqMEVuSTR5K3d0VElHOWQ2M1EyenowOGZLQURKaDNiQytYNVBWOFZObw", self::ArrayRawData];
        yield "unserialized string" => ["MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09", self::RawData];
    }

    /** @dataProvider dataForTestDecrypt1 */
    public function testDecrypt1(string $encrypted, mixed $expected): void
    {
        self::assertEquals($expected, $this->instance->decrypt($encrypted));
    }

    /** Ensure decrypt() throws when the encrypted bse64 is not valid. */
    public function testDecrypt2(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data");
        $this->instance->decrypt("MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09===");
    }

    /** Ensure decrypt() throws with truncated encrypted data. */
    public function testDecrypt3(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (truncated)");
        $this->instance->decrypt("MTExMTEx");
    }

    /** Ensure decrypt() throws when the serialization flag in the encrypted data is not valid. */
    public function testDecrypt4(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encrypted data (bad serialization flag)");
        // this base64 has S where the serialization flag should be
        $this->instance->decrypt("MTExMTExMTFTNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09");
    }

    /** Ensure decrypt() throws when OpenSSL fails. */
    public function testDecrypt5(): void
    {
        $this->mockFunction("openssl_decrypt", fn (): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Unable to decrypt data");
        $this->instance->decrypt("MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09");
    }

    /** Ensure decrypt() throws when unserialize() fails. */
    public function testDecrypt6(): void
    {
        $this->mockFunction("unserialize", fn (): bool => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("The decrypted data could not be unserialized");
        $this->instance->decrypt("MTExMTExMTFZNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09");
    }

    /**
     * Ensure decrypting the encrypted value `false` works.
     *
     * Since serialize() returns false both when it fails and when it successfully unserializes the serialization of
     * false, we need a test to prove unserializing false works as expected.
     */
    public function testDecrypt7(): void
    {
        self::assertFalse($this->instance->decrypt("MTExMTExMTFZTUNKMTcxZ2ltTlk9"));
    }
}
