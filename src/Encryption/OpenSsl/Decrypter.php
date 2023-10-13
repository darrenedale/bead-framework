<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Contracts\Encryption\Decrypter as DecrypterContract;
use Bead\Encryption\HasKey;
use Bead\Encryption\Sodium\ChecksKey;
use Bead\Exceptions\EncryptionException;

/**
 * Perform decryption using OpenSSL.
 */
class Decrypter implements DecrypterContract
{
    use ChecksKey;
	use Decrypts;
    use HasAlgorithm;
	use HasKey;

	/**
	 * Initialise a new Decrypter
	 *
	 * @throws EncryptionException if the algorithm is not supported or the key is not valid.
	 */
	public function __construct(string $algorithm, string $key)
	{
        $this->setAlgorithm($algorithm);
        self::checkKey($key);
		$this->key = $key;
	}
}
