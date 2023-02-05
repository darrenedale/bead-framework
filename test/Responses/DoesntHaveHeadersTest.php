<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\DoesntHaveHeaders;
use BeadTests\Framework\TestCase;

class DoesntHaveHeadersTest extends TestCase
{
	/** Helper to create a new instance of a class that imports the trait under test. */
	private function createInstance(): mixed
	{
		return new class
		{
			use DoesntHaveHeaders;
		};
	}

	/** Ensure the headers are an empty array. */
	public function testHeaders(): void
	{
		self::assertEquals([], $this->createInstance()->headers());
	}
}
