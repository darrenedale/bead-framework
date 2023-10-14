<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Exceptions\EncryptionException;
use LogicException;

/**
 * Shared implementation of management of the cryptographic key to use when encrypting/decrypting data.
 */
trait HasKey
{
    use ScrubsStrings;

    private string $key = "";

    /**
     * The stored key is securely erased, so that it remains in memory no longer than necessary.
     */
    public function __destruct()
    {
        self::scrubString($this->key);
    }

    /**
     * Classes that call this should ensure the variable where it's stored is scrubbed when done.
     *
     * @return string The encryption key.
     */
    private function key(): string
    {
        assert("" !== $this->key, new LogicException("No encryption key has been set"));
        return $this->key;
    }
}
