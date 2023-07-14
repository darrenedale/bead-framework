<?php

declare(strict_types=1);

namespace Bead\Encryption;

trait GeneratesRandomBytes
{
	private static function randomBytes(int $len): string
	{
		return random_bytes($len);
	}
}
