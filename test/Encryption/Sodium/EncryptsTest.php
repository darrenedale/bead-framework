<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Encryption\SerializationMode;
use Bead\Encryption\Sodium\Encrypts;
use Bead\Exceptions\EncryptionException;
use BeadTests\Framework\TestCase;
use SodiumException;

class EncryptsTest extends TestCase
{
    private const RawData = "the-data";

    private const ArrayRawData = ["the-data", "more-data"];

    /** @var object $instance An instance of an anonymous class that utilises the trait. */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use Encrypts;

            private function randomBytes(int $len): string
            {
                return "000011112222333344445555";
            }

            public function key(): string
            {
                return "-some-insecure-key-insecure-some";
            }
        };
    }

    public static function dataForTestEncrypt1(): iterable
    {
        yield "auto-serialization-string" => [self::RawData, SerializationMode::Auto, "MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg=="];
        yield "auto-serialization-array" => [self::ArrayRawData, SerializationMode::Auto, "MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WeAelQVLV8IHIWXYsUXxm95ZfdnvELEzY1npRj1hPCebFKtX+vA11J5LQTo9qBPjRhbCQJe+XTtruh9E4rY="];
        yield "forced-serialization-string" => [self::RawData, SerializationMode::On, "MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg="];
        yield "no-serialization-string" => [self::RawData, SerializationMode::Off, "MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg=="];
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
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->instance->encrypt(self::RawData));
        // array will
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WeAelQVLV8IHIWXYsUXxm95ZfdnvELEzY1npRj1hPCebFKtX+vA11J5LQTo9qBPjRhbCQJe+XTtruh9E4rY=", $this->instance->encrypt(self::ArrayRawData));
    }

    /** Ensure encrypt() throws with an invalid serialization mode. */
    public function testEncrypt3(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid serialization mode");
        $this->instance->encrypt(self::RawData, -1);
    }

    /** Ensure encrypt() throws when encryption fails. */
    public function testEncrypt4(): void
    {
        /** @psalm-suppress NoValue */
        self::mockFunction("sodium_bin2base64", fn (string $data): string => throw new SodiumException("Test exception."));
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Exception encrypting data: Test exception.");
        $this->instance->encrypt(self::RawData);
    }
}
