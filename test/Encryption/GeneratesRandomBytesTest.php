<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Encryption\GeneratesRandomBytes;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class GeneratesRandomBytesTest extends TestCase
{
    /** @var XRay&GeneratesRandomBytes */
    private XRay $instance;

    public function setUp(): void
    {
        $instance = new class {
            use GeneratesRandomBytes;
        };

        $this->instance = new XRay($instance);
    }

    /** Ensure random_bytes() can be used to generate random bytes. */
    public function testGenerateRandomBytes1(): void
    {
        $this->mockFunction("random_bytes", function (int $len): string {
            TestCase::assertEquals(64, $len);
            return str_repeat("X", $len);
        });

        self::assertEquals(str_repeat("X", 64), $this->instance->randomBytes(64));
    }

    /** Ensure openssl_random_pseudo_bytes() is used when random_bytes() is not available. */
    public function testGenerateRandomBytes2(): void
    {
        $this->mockFunction("function_exists", function (string $fn): bool {
            return match ($fn) {
                "random_bytes" => false,
                default => true,
            };
        });

        $this->mockFunction("openssl_random_pseudo_bytes", function (int $len, bool & $strong): string {
            TestCase::assertEquals(64, $len);
            $strong = true;
            return str_repeat("X", $len);
        });

        self::assertEquals(str_repeat("X", 64), $this->instance->randomBytes(64));
    }

    /** Ensure an exception is thrown when openssl_random_pseudo_bytes indicates the randomness is not strong. */
    public function testGenerateRandomBytes3(): void
    {
        $this->mockFunction("function_exists", function (string $fn): bool {
            return match ($fn) {
                "random_bytes" => false,
                default => true,
            };
        });

        $this->mockFunction("openssl_random_pseudo_bytes", function (int $len, bool & $strong): string {
            TestCase::assertEquals(64, $len);
            $strong = false;
            return str_repeat("X", $len);
        });

        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Cryptographically secure random bytes are not available on this platoform");
        $this->instance->randomBytes(64);
    }
}
