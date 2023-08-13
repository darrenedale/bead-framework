<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Encryption\OpenSsl\HasAlgorithm;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use LogicException;

class HasAlgorithmTest extends TestCase
{
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use HasAlgorithm;
        };
    }

    public function testAlgorithm(): void
    {
        $xray = new XRay($this->instance);
        $xray->algorithm = "des-ede3-cbc";
        self::assertEquals("des-ede3-cbc", $this->instance->algorithm());
    }

    public function testAlgorithmThrows(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Cipher algorithm has not been set");
        $this->instance->algorithm();
    }

    public function testSetAlgorithm(): void
    {
        $this->instance->setAlgorithm("des-ede3-cbc");
        self::assertEquals("des-ede3-cbc", $this->instance->algorithm());
    }

    public function testSetAlgorithmThrows(): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Cipher algorithm 'this-algorithm-does-not-exist' is not supported");
        $this->instance->setAlgorithm("this-algorithm-does-not-exist");
    }
}
