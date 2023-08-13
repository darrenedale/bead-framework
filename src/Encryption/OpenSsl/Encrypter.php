<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Contracts\Encryption\Encrypter as EncrypterContract;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\HasKey;

class Encrypter implements EncrypterContract
{
    use ChecksKey;
	use Encrypts;
	use GeneratesRandomBytes;
    use HasAlgorithm;
	use HasKey;

	public function __construct(string $algorithm, string $key)
	{
        $this->setAlgorithm($algorithm);
        self::checkKey($key);
		$this->key = $key;
	}
}
