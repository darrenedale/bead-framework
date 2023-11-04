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
    use ProvidesOpenSslSupportedAlgorithms;
    use ProvidesOpenSslUnsupportedAlgorithms;

    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use HasAlgorithm;
        };
    }

    /** Ensure the trait returns the expected algorithm. */
    public function testAlgorithm1(): void
    {
        $xray = new XRay($this->instance);
        $xray->algorithm = "des-ede3-cbc";
        self::assertEquals("des-ede3-cbc", $this->instance->algorithm());
    }

    /** Ensure algorithm() throws when the algorithm has not been set. */
    public function testAlgorithm2(): void
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage("Cipher algorithm has not been set");
        $this->instance->algorithm();
    }

    public static function dataForTestSetAlgorithm1(): iterable
    {
        yield from self::openSslSupportedAlgorithms();
    }

    /**
     * Ensure the algorithm can be set successfully.
     *
     * @dataProvider dataForTestSetAlgorithm1
     */
    public function testSetAlgorithm1(string $algorithm): void
    {
        $this->instance->setAlgorithm($algorithm);
        self::assertEquals($algorithm, $this->instance->algorithm());
    }

    public static function dataForTestSetAlgorithm2(): iterable
    {
        yield from self::openSslUnsupportedAlgorithms();
    }

    /**
     * Ensure setAlgorithm() throws when the algorithm is not supported.
     *
     * @dataProvider dataForTestSetAlgorithm2
     */
    public function testSetAlgorithm2(string $algorithm): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Cipher algorithm '{$algorithm}' is not supported");
        $this->instance->setAlgorithm($algorithm);
    }
}
