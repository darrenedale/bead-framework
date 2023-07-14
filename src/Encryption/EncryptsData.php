<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Contracts\Encryption\SerializationMode;
use Exception;

trait EncryptsData
{
	private abstract function key(): string;

	private static abstract function randomBytes(int $len): string;

	public function encrypt(mixed $data, int $serializationMode = SerializationMode::Auto): string
	{
		$serialized = 'N';

		switch ($serializationMode) {
			case SerializationMode::Auto:
				if (!is_string($data)) {
					$serialized = 'Y';
					$data = serialize($data);
				}
				break;

			case SerializationMode::On:
				$serialized = 'Y';
				$data = serialize($data);
				break;

			case SerializationMode::Off:
				break;

			default:
				throw new Exception('Invalid serialization mode');
		};

		$nonce = self::randomBytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

		$encrypted = base64_encode(
			$nonce .
			$serialized .
			sodium_crypto_secretbox($data, $nonce, $this->key())
		);

		sodium_memzero($data);
		sodium_memzero($serialized);
		return $encrypted;
	}
}
