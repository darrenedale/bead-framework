<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Exceptions\EncryptionException;
use LogicException;

trait HasEncryptionKey
{
	private string $key = "";

	public function __destruct()
	{
		sodium_memzero($this->key);
	}

    private static function checkKey(string $key): void
    {
        if (SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== mb_strlen($key, "8bit")) {
            throw new EncryptionException("Invalid encryption key");
        }
    }

	private function key(): string
	{
		assert("" !== $this->key, new LogicException("No encryption key has been set"));
		return $this->key;
	}
}
