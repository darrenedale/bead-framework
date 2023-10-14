<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\HasKey;
use Bead\Exceptions\EncryptionException;

/**
 * Perform both encryption and decryption using Sodium.
 */
class Crypter implements CrypterContract
{
	use HasKey;
	use ChecksKey;
	use Encrypts;
	use Decrypts;
	use GeneratesRandomBytes;

	/**
	 * Initialise a new Crypter
	 *
	 * @throws EncryptionException if the the key is not valid.
	 */
	public function __construct(string $key)
	{
        self::checkKey($key);
		$this->key = $key;
	}
}
