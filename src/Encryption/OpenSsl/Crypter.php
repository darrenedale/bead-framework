<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Contracts\Encryption\Crypter as CrypterContract;
use Bead\Encryption\GeneratesRandomBytes;
use Bead\Encryption\HasKey;
use Bead\Exceptions\EncryptionException;

/**
 * Perform both encryption and decryption using OpenSSL.
 */
class Crypter implements CrypterContract
{
    use HasAlgorithm;
    use HasKey;
    use ChecksKey;
    use Encrypts;
    use Decrypts;
    use GeneratesRandomBytes;

    /**
     * Initialise a new Crypter
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
