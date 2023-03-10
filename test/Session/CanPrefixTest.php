<?php

declare(strict_types=1);

namespace BeadTests\Session;

use Bead\Session\CanPrefix;
use Bead\Session\DataAccessor;
use Bead\Session\PrefixedAccessor;
use BedTests\Framework\TestCase;

final class CanPrefixTest extends TestCase
{
	/** @var CanPrefix */
	private $instance;

	public function setUp(): void
	{
		$this->instance = new class implements DataAccessor {
			use CanPrefix;

			public function all(): array
			{
				return [];
			}

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

			public function set(string|array $keyOrData, mixed $data = null): void
			{}

			public function push(string $key, mixed $data): void
			{}

			public function pushAll(string $key, array $data): void
			{}

			public function pop(string $key, int $n = 1): mixed
			{
				return null;
			}

			public function prefixed(string $prefix): DataAccessor
			{
				return new PrefixedAccessor($prefix, $this);
			}

			public function transientSet(string|array $keyOrData, mixed $data = null): void
			{}

			public function remove(string|array $keys): void
			{}
		};
	}

	/** Ensure we get a PrefixedAccessor instance from prefixed() method. */
	public function testPrefixed(): void
	{
		self::assertInstanceOf(PrefixedAccessor::class, $this->instance->prefixed("bead."));
	}
}
