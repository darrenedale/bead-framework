<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\HasKey;

class Crypter implements CrypterContract
{
    use HasAlgorithm;
	use HasKey;
    use ChecksKey;
	use Encrypts;
	use Decrypts;
	use GeneratesRandomBytes;

	public function __construct(string $algorithm, string $key)
	{
        $this->setAlgorithm($algorithm);
        self::checkKey($key);
		$this->key = $key;
	}
}
