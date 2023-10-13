<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Contracts\Encryption\Decrypter as DecrypterContract;
use Bead\Encryption\HasKey;
use Bead\Exceptions\EncryptionException;

/**
 * Perform decryption using Sodium.
 */
class Decrypter implements DecrypterContract
{
	use HasKey;
    use ChecksKey;
	use Decrypts;

	/**
	 * Initialise a new Decrypter
	 *
	 * @throws EncryptionException if the the key is not valid.
	 */
	public function __construct(string $key)
	{
        self::checkKey($key);
		$this->key = $key;
	}
}
