<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Exceptions\EncryptionException;
use LogicException;

trait HasAlgorithm
{
    private static ?array $availableCiphers = null;

    private string $algorithm = "";

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

    public function algorithm(): string
    {
        assert("" !== $this->algorithm, new LogicException("Cipher algorithm has not been set"));
        return $this->algorithm;
    }
}
