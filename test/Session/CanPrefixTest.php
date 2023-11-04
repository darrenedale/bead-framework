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

            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function extract(string|array $keys): array
            {
                return [];
            }

            public function all(): array
            {
                return [];
            }

            public function set(string|array $keyOrData, mixed $data = null): void
            {
            }

            public function push(string $key, mixed $data): void
            {
            }

            public function pushAll(string $key, array $data): void
            {
            }

            public function pop(string $key, int $n = 1): mixed
            {
                return null;
            }

            public function transientSet(string|array $keyOrData, mixed $data = null): void
            {
            }

            public function remove(string|array $keys): void
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
