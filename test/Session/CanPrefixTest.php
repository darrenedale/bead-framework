<?php

declare(strict_types=1);

namespace BeadTests\Session;

use Bead\Session\CanPrefix;
use Bead\Session\DataAccessor;
use Bead\Session\ImplementsArrayAccess;
use Bead\Session\PrefixedAccessor;
use BeadTests\Framework\TestCase;

final class CanPrefixTest extends TestCase
{
    private $instance;

    public function setUp(): void
    {
        $this->instance = new class implements DataAccessor
        {
            use CanPrefix;
            use ImplementsArrayAccess;

            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key, $default = null)
            {
                return null;
            }

            public function extract($keys)
            {
                return [];
            }

            public function all(): array
            {
                return [];
            }

            public function set($keyOrData, $data = null): void
            {
            }

            public function push(string $key, $data): void
            {
            }

            public function pushAll(string $key, array $data): void
            {
            }

            public function pop(string $key, int $n = 1)
            {
                return null;
            }

            public function transientSet($keyOrData, $data = null): void
            {
            }

            public function remove($keys): void
            {
            }
        };
    }

    public function tearDown(): void
    {
        unset($this->instance);
        parent::tearDown();
    }

    /** Ensure prefixed() returns a new PrefixedAccessor  */
    public function testPrefixed(): void
    {
        $actual = $this->instance->prefixed("test-prefix");
        self::assertInstanceOf(PrefixedAccessor::class, $actual);
        self::assertNotSame($actual, $this->instance);
    }
}
