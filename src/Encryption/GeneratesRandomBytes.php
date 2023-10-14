<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Exceptions\EncryptionException;
use LogicException;

/**
 * Shared implementation of generation of cryptographicall-secure random byttes.
 */
trait GeneratesRandomBytes
{
    /**
     * Get some cryptographically-secure random bytes.
     *
     * @param int $len How many bytes.
     *
     * @return string The random bytes generated.
     * @throws EncryptionException if a source of cryptographically-secure random bytes is not available.
     */
    private function randomBytes(int $len): string
    {
        assert(0 < $len, new LogicException("Length of random bytes must be > 0"));

        if (function_exists("random_bytes")) {
            return random_bytes($len);
        } else if (function_exists("openssl_random_pseudo_bytes")) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes($len, $strong);

            if ($strong) {
                return $bytes;
            }
        }

        throw new EncryptionException("Cryptographically secure random bytes are not available on this platoform");
    }
}
