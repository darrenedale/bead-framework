<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Contracts\Encryption\Decrypter as DecrypterContract;
use Bead\Encryption\HasKey;
use Bead\Encryption\Sodium\ChecksKey;

class Decrypter implements DecrypterContract
{
    use ChecksKey;
	use Decrypts;
    use HasAlgorithm;
	use HasKey;

	public function __construct(string $algorithm, string $key)
	{
        $this->setAlgorithm($algorithm);
        self::checkKey($key);
		$this->key = $key;
	}
}
