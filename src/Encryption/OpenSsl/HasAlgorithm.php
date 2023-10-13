<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Exceptions\EncryptionException;
use LogicException;

/**
 * Shared implementation of management of the OpenSSL algorithm.
 */
trait HasAlgorithm
{
    private static ?array $availableCiphers = null;

    private string $algorithm = "";

	/**
	 * Set the OpenSSL algorithm to use for encryption/decryption.
	 *
	 * @param string $algorithm
	 *
	 * @throws EncryptionException if the algorithm is not supported.
	 */
    public function setAlgorithm(string $algorithm): void
    {
        if (!isset(self::$availableCiphers)) {
            self::$availableCiphers = openssl_get_cipher_methods();
        }

        if (!in_array($algorithm, self::$availableCiphers)) {
            throw new EncryptionException("Cipher algorithm '{$algorithm}' is not supported");
        }

        $this->algorithm = $algorithm;
    }

	/**
	 * @return string The OpenSSL algorithm to use for encryption/decryption.
	 */
    public function algorithm(): string
    {
        assert("" !== $this->algorithm, new LogicException("Cipher algorithm has not been set"));
        return $this->algorithm;
    }
}
