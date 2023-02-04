<?php

declare(strict_types=1);

namespace Responses;

use Bead\Responses\DoesntHaveContent;
use PHPUnit\Framework\TestCase;

class DoesntHaveContentTest extends TestCase
{
	/** Helper to create a new instance of a class that imports the trait under test. */
	private function createInstance(): mixed
	{
		return new class
		{
			use DoesntHaveContent;
		};
	}

	/** Ensure the content-type is an empty string. */
	public function testContentType(): void
	{
		self::assertEquals("", $this->createInstance()->contentType());
	}

	/** Ensure the content is an empty string. */
	public function testContent(): void
	{
		self::assertEquals("", $this->createInstance()->content());
	}
}
