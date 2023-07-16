<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Contracts\Encryption\Encrypter as EncrypterContract;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\HasKey;

class Encrypter implements EncrypterContract
{
	use HasKey;
    use ChecksKey;
	use Encrypts;
	use GeneratesRandomBytes;

	public function __construct(string $key)
	{
        self::checkKey($key);
		$this->key = $key;
	}
}
