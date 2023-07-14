<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Exception;

trait DecryptsData
{
	private abstract function key(): string;

	public function decrypt(string $data): mixed
	{
		$data = base64_decode($data, true);

		if (false === $data) {
			throw new Exception('Invalid encrypted data');
		}

		if (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES + 1 > mb_strlen($data, '8bit')) {
			throw new Exception('Invalid encrypted data');
		}

		$nonce = mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
		$serialized = mb_substr($data,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, 1, '8bit');
		$data = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1, null, '8bit');

		if (!in_array($serialized, ['Y', 'N'])) {
			throw new Exception('Invalid encrypted data');
		}

		try {
			$decrypted = sodium_crypto_secretbox_open($data, $nonce, $this->key());
		} catch (SodiumException $err) {
			throw new Exception("Exception decrypting data: {$err->getMessage()}", previous: $err);
		}

		if (false === $decrypted) {
			throw new Exception('Unable to decrypt data');
		}

		sodium_memzero($data);

		if ('Y' === $serialized) {
			# we do this so that we can be sure that unserialize() returning false indicates an error
			if (serialize(false) === $decrypted) {
				return false;
			}

            $decrypted = unserialize($decrypted);

			if (false === $decrypted) {
				throw new Exception('The decrypted data could not be unserialized');
			}
		}

		return $decrypted;
	}
}
