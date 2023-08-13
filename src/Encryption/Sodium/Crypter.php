<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\HasKey;

class Crypter implements CrypterContract
{
	use HasKey;
    use ChecksKey;
	use Encrypts;
	use Decrypts;
	use GeneratesRandomBytes;

	public function __construct(string $key)
	{
        self::checkKey($key);
		$this->key = $key;
	}
}
