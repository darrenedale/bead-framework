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
    /** @var object $instance Instance of an anonymous class that utilises the HasKey trait. */
    private object $instance;

    public function setUp(): void
    {
        $this->instance = new class {
            use HasKey {
                scrubString as traitScrubString;
            }

            public static bool $scrubStringCalled = false;

            private static function scrubString(string & $str): void
            {
                self::$scrubStringCalled = true;
                self::traitScrubString($str);
            }
        };
    }

    /** Ensure we get the expected key. */
    public function testKey1(): void
    {
        $instance = new XRay($this->instance);
        $instance->key = "something";
        self::assertEquals("something", $instance->key());
    }

    /** Ensure key() throws when the key is empty. */
    public function testKey2(): void
    {
        $instance = new XRay($this->instance);
        self::expectException(LogicException::class);
        self::expectExceptionMessage("No encryption key has been set");
        $instance->key();
    }

    /** Ensure the destructor scrubs the key. */
    public function testDestructor1(): void
    {
        /** @var class-string $instanceClass */
        $instanceClass = $this->instance::class;
        self::assertFalse($instanceClass::$scrubStringCalled);
        $instance = new XRay($this->instance);
        $instance->key = "something";
        unset($instance, $this->instance);
        self::assertTrue($instanceClass::$scrubStringCalled);
    }
}
