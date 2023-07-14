<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Contracts\Encryption\Encrypter as EncrypterContract;
use Exception;

class Encrypter implements EncrypterContract
{
	use HasEncryptionKey;
	use EncryptsData;
	use GeneratesRandomBytes;

	public function __construct(string $key)
	{
        self::checkKey($key);
		$this->key = $key;
	}
}
