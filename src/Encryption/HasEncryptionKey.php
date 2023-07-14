<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Exception;

trait HasEncryptionKey
{
	private string $key = '';

	public function __destruct()
	{
		sodium_memzero($this->key);
	}

	private function key(): string
	{
		assert('' !== $this->key, new Exception('No encryption key has been set.'));
		return $this->key;
	}
}
