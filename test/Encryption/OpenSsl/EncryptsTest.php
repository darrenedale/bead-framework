<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\OpenSsl\Encrypts;
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

    public function testEncrypt(): void
    {
        self::assertEquals("MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09", $this->instance->encrypt(self::RawData));
    }

    public function testEncryptDoesntAutoSerializeString(): void
    {
        self::assertEquals("MTExMTExMTFONVFWNVhvU2pqRVhuWTUyZS9UTHJBdz09", $this->instance->encrypt(self::RawData, SerializationMode::Auto));
    }

    public function testEncryptAutoSerializes(): void
    {
        self::assertEquals("MTExMTExMTFZZlJGNXR5bCtjVGhqWkd2OXVRT1ZPV0tqMEVuSTR5K3d0VElHOWQ2M1EyenowOGZLQURKaDNiQytYNVBWOFZObw==", $this->instance->encrypt(self::ArrayRawData, SerializationMode::Auto));
    }

    public function testEncryptForceSerializes(): void
    {
        self::assertEquals("MTExMTExMTFZNUlNYmY5ZUE4bC9xQmFBUmxBMFRkUT09", $this->instance->encrypt(self::RawData, SerializationMode::On));
    }
}
