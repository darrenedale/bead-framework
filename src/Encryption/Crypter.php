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
        self::checkKey($key);
		$this->key = $key;
	}
}
