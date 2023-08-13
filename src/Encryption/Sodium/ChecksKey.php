<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Exceptions\EncryptionException;

trait ChecksKey
{
    private static function checkKey(string $key): void
    {
        if (SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== mb_strlen($key, "8bit")) {
            throw new EncryptionException("Invalid encryption key");
        }
    }
}
