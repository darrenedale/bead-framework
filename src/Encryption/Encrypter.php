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
		assert('' !== $key, new Exception('The encryption key must not be empty'));
		$this->key = $key;
	}
}
