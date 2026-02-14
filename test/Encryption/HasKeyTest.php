<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Encryption\HasKey;
use Bead\Exceptions\EncryptionException;
use BeadTests\Framework\TestCase;
use Equit\XRay\StaticXRay;
use Equit\XRay\XRay;
use LogicException;

class HasKeyTest extends TestCase
{
    /** @var object $instance Instance of an anonymous class that utilises the HasKey trait. */
    private object $instance;

    protected function setUp(): void
    {
        $this->instance = new class {
            use HasKey {
                scrubString as traitScrubString;
            }

            static array $listeners = [];

            public static function addListener(callable $listener): void
            {
                self::$listeners[] = $listener;
            }

            public static function clearListeners(): void
            {
                self::$listeners = [];
            }

            private static function scrubString(string & $str): void
            {
                foreach (self::$listeners as $listener) {
                    $listener($str);
                }

                self::traitScrubString($str);
            }
        };
    }

    protected function tearDown(): void
    {
        if (isset($this->instance)) {
            $this->instance->clearListeners();
        }

        unset($this->instance);
        parent::tearDown();
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
        $instance = new XRay($this->instance);
        $instance->key = "something";
        $scrubCalled = false;

        $this->instance->addListener(static function (string $str) use (&$scrubCalled): void {
            TestCase::assertSame("something", $str);
            $scrubCalled = true;
        });

        unset($instance, $this->instance);
        self::assertTrue($scrubCalled);
    }
}
