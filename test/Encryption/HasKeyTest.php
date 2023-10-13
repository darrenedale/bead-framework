<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Encryption\HasKey;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\StaticXRay;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use LogicException;

class HasKeyTest extends TestCase
{
    /** @var HasKey */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use HasKey;
        };
    }

    public function testKey(): void
    {
        $instance = new XRay($this->instance);
        $instance->key = "something";
        self::assertEquals("something", $instance->key());
    }


    public function testKeyThrowsWhenEmpty(): void
    {
        $instance = new XRay($this->instance);
        self::expectException(LogicException::class);
        self::expectExceptionMessage("No encryption key has been set");
        $instance->key();
    }

    public function testDestructor(): void
    {
        $instance = new XRay($this->instance);
        $instance->key = "something";
        unset($instance);

        self::mockFunction("sodium_memzero", function(string &$str): void {
            TestCase::assertEquals("something", $str);
        });

        unset($this->instance);
    }
}
