<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Encryption\SerializationMode;
use Bead\Encryption\OpenSsl\Encrypts;
use Bead\Exceptions\EncryptionException;
use BeadTests\Framework\TestCase;

class EncryptsTest extends TestCase
{
    private const RawData = "the-data";

    private const ArrayRawData = ["the-data", "more-data"];

    /** @var Encrypts */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use Encrypts;

            public function algorithm(): string
            {
                return "des-ede-cbc";
            }

            private function randomBytes(int $len): string
            {
                return str_repeat("1", $len);
            }

            public function key(): string
            {
                return "-some-insecure-key";
            }
        };
    }

    public static function dataForTestEncrypt1(): iterable
    {
        yield "auto-serialization-string" => [self::RawData, SerializationMode::Auto, "MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09"];
        yield "auto-serialization-array" => [self::ArrayRawData, SerializationMode::Auto, "MTExMTExMTFZZlJGNXR5bCtjVGhqWkd2OXVRT1ZPV0tqMEVuSTR5K3d0VElHOWQ2M1EyenowOGZLQURKaDNiQytYNVBWOFZObw=="];
        yield "forced-serialization-string" => [self::RawData, SerializationMode::On, "MTExMTExMTFZNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09"];
        yield "no-serialization-string" => [self::RawData, SerializationMode::Off, "MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09"];
    }

    /** @dataProvider dataForTestEncrypt1 */
    public function testEncrypt1(mixed $data, int $serializationMode, string $expected): void
    {
        self::assertEquals($expected, $this->instance->encrypt($data, $serializationMode));
    }

    /** Ensure default serialisation mode is auto */
    public function testEncrypt2(): void
    {
        // string won't be serialized
        self::assertEquals("MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09", $this->instance->encrypt(self::RawData));
        // array will
        self::assertEquals("MTExMTExMTFZZlJGNXR5bCtjVGhqWkd2OXVRT1ZPV0tqMEVuSTR5K3d0VElHOWQ2M1EyenowOGZLQURKaDNiQytYNVBWOFZObw==", $this->instance->encrypt(self::ArrayRawData));
    }

    /** Ensure encrypt() throws with an invalid serialization mode. */
    public function testEncrypt3(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid serialization mode");
        $this->instance->encrypt(self::RawData, -1);
    }

    /** Ensure encrypt() throws when base64 encoding fails. */
    public function testEncrypt4(): void
    {
        self::mockFunction('base64_encode', fn(string $data): string|false => false);
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Unable to encrypt data");
        $this->instance->encrypt(self::RawData);
    }
}
