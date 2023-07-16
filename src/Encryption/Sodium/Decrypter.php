<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Contracts\Encryption\Decrypter as DecrypterContract;
use Bead\Encryption\HasKey;

class Decrypter implements DecrypterContract
{
	use HasKey;
    use ChecksKey;
	use Decrypts;

	public function __construct(string $key)
	{
        self::checkKey($key);
		$this->key = $key;
	}
}
