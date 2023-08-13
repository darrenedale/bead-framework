<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\Sodium\Encrypts;
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

    public function testEncrypt(): void
    {
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->instance->encrypt(self::RawData));
    }

    public function testEncryptDoesntAutoSerializeString(): void
    {
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1TpemMPGVdvhZEWHg8TV56ItML474D7l9Mg==", $this->instance->encrypt(self::RawData, SerializationMode::Auto));
    }

    public function testEncryptAutoSerializes(): void
    {
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WeAelQVLV8IHIWXYsUXxm95ZfdnvELEzY1npRj1hPCebFKtX+vA11J5LQTo9qBPjRhbCQJe+XTtruh9E4rY=", $this->instance->encrypt(self::ArrayRawData, SerializationMode::Auto));
    }

    public function testEncryptForceSerializes(): void
    {
        self::assertEquals("MDAwMDExMTEyMjIyMzMzMzQ0NDQ1NTU1WQRXm7dZGM4UM/YhV554l2VLfdPvSaxhNk/+HXE6PGg=", $this->instance->encrypt(self::RawData, SerializationMode::On));
    }
}
