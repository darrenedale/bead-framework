<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Contracts\Encryption\Decrypter as DecrypterContract;
use Exception;

class Decrypter implements DecrypterContract
{
	use HasEncryptionKey;
	use DecryptsData;

	public function __construct(string $key)
	{
        self::checkKey($key);
		$this->key = $key;
	}
}
