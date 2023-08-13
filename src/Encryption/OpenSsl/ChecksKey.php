<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Exceptions\EncryptionException;

trait ChecksKey
{
    private const MinimumKeyLength = 24;

    private static function checkKey(string $key): void
    {
        if (self::MinimumKeyLength > mb_strlen($key, "8bit")) {
            throw new EncryptionException("Invalid encryption key");
        }
    }
}
