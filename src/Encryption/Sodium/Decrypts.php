<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Exceptions\EncryptionException;
use SodiumException;

/**
 * Trait to decrypt data encrypted using Sodium.
 */
trait Decrypts
{
    /** Classes utilising the trait must provide an encryption key. */
    abstract private function key(): string;

    /**
     * @throws EncryptionException if:
     * - the provided encrypted data is not valid
     * - Sodium throws during decryption or is unable to decrypt the data; or
     * - the encrypted data is serailized but cannot be unserialized
     */
    public function decrypt(string $data): mixed
    {
        try {
            $data = sodium_base642bin($data, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException $err) {
            throw new EncryptionException("Invalid encrypted data: {$err->getMessage()}", previous: $err);
        }

        if (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES + 1 > mb_strlen($data, "8bit")) {
            throw new EncryptionException("Invalid encrypted data (truncated)");
        }

        $nonce = mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, "8bit");
        $serialized = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, 1, "8bit");
        $data = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1, null, "8bit");

        if (!in_array($serialized, ["Y", "N"])) {
            throw new EncryptionException("Invalid encrypted data (bad serialization flag)");
        }

        try {
            $decrypted = sodium_crypto_secretbox_open($data, $nonce, $this->key());
        } catch (SodiumException $err) {
            throw new EncryptionException("Exception decrypting data: {$err->getMessage()}", previous: $err);
        }

        if (false === $decrypted) {
            throw new EncryptionException("Unable to decrypt data");
        }

        sodium_memzero($data);

        if ("Y" === $serialized) {
            // we do this so that we can be sure that unserialize() returning false indicates an error
            if (serialize(false) === $decrypted) {
                return false;
            }

            $decrypted = unserialize($decrypted);

            if (false === $decrypted) {
                throw new EncryptionException("The decrypted data could not be unserialized");
            }
        }

        return $decrypted;
    }
}
