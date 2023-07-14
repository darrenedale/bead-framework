<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Exception;

class Crypter implements CrypterContract
{
	use HasEncryptionKey;
	use EncryptsData;
	use DecryptsData;
	use GeneratesRandomBytes;

	public function __construct(string $key)
	{
		assert('' !== $key, new Exception('The encryption key must not be empty'));
		$this->key = $key;
	}
}
