<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Exceptions\EncryptionException;
use LogicException;

trait HasKey
{
	private string $key = "";

	public function __destruct()
	{
		sodium_memzero($this->key);
	}

	private function key(): string
	{
		assert("" !== $this->key, new LogicException("No encryption key has been set"));
		return $this->key;
	}
}
