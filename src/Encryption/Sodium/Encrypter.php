<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Contracts\Encryption\Encrypter as EncrypterContract;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\HasKey;
use Bead\Exceptions\EncryptionException;

/**
 * Perform encryption using Sodium.
 */
class Encrypter implements EncrypterContract
{
	use HasKey;
	use ChecksKey;
	use Encrypts;
	use GeneratesRandomBytes;

	/**
	 * Initialise a new Encrypter
	 *
	 * @throws EncryptionException if the the key is not valid.
	 */
	public function __construct(string $key)
	{
		self::checkKey($key);
		$this->key = $key;
	}
}
