<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Contracts\Encryption\SerializationMode;
use Bead\Exceptions\EncryptionException;
use SodiumException;

trait EncryptsData
{
	private abstract function key(): string;

	private abstract function randomBytes(int $len): string;

	public function encrypt(mixed $data, int $serializationMode = SerializationMode::Auto): string
	{
		$serialized = "N";

		switch ($serializationMode) {
			case SerializationMode::Auto:
				if (!is_string($data)) {
					$serialized = "Y";
					$data = serialize($data);
				}
				break;

			case SerializationMode::On:
				$serialized = "Y";
				$data = serialize($data);
				break;

			case SerializationMode::Off:
				break;

			default:
				throw new EncryptionException("Invalid serialization mode");
		};

		$nonce = $this->randomBytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $encrypted = base64_encode(
                $nonce .
                $serialized .
                sodium_crypto_secretbox($data, $nonce, $this->key())
            );
        } catch (SodiumException $err) {
            throw new EncryptionException("Exception encrypting data: {$err->getMessage()}", previous: $err);
        }

		sodium_memzero($data);
		sodium_memzero($serialized);
		return $encrypted;
	}
}
