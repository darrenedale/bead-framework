<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Encryption\SerializationMode;
use Bead\Exceptions\EncryptionException;

trait Encrypts
{
    use ScrubsStrings;

    public abstract function algorithm(): string;

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

        $algorithm = $this->algorithm();
        $ivLength = openssl_cipher_iv_length($algorithm);
		$iv = $this->randomBytes($ivLength);

        $encrypted = base64_encode(
            $iv .
            $serialized .
            openssl_encrypt($data, $algorithm, $this->key(), 0, $iv)
        );

        if (false === $encrypted) {
            throw new EncryptionException("Unable to encrypt data");
        }

        self::scrubString($data);
        self::scrubString($serialized);
		return $encrypted;
	}
}
