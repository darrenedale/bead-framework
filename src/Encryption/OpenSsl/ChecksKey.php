<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Exceptions\EncryptionException;

/**
 * Trait for establishing whether a key is valid for use with OpenSSL encryption/decryption.
 */
trait ChecksKey
{
    /** Valid keys have at least this many bytes */
    private static function minimumKeyLength(): int
    {
        return 24;
    }

    /** Ensure the provided key is valid. */
    private static function checkKey(string $key): void
    {
        if (self::minimumKeyLength() > mb_strlen($key, "8bit")) {
            throw new EncryptionException("Invalid encryption key");
        }
    }
}
