<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

use Bead\Encryption\ScrubsStrings;
use Bead\Exceptions\EncryptionException;

/**
 * Trait to decrypt data encrypted using OpenSSL.
 */
trait Decrypts
{
    use ScrubsStrings;

    abstract public function algorithm(): string;

    /** Classes utilising the trait must provide an encryption key. */
    abstract private function key(): string;

    /**
     * @throws EncryptionException if:
     * - the provided encrypted data is not valid
     * - OpenSSL is unable to decrypt the data; or
     * - the encrypted data is serailized but cannot be unserialized
     */
    public function decrypt(string $data): mixed
    {
        $data = base64_decode($data, true);

        if (false === $data) {
            throw new EncryptionException("Invalid encrypted data");
        }

        $algorithm = $this->algorithm();
        $ivLength = openssl_cipher_iv_length($algorithm);

        if ($ivLength + 1 > mb_strlen($data, "8bit")) {
            throw new EncryptionException("Invalid encrypted data (truncated)");
        }

        $iv = mb_substr($data, 0, $ivLength, "8bit");
        $serialized = mb_substr($data, $ivLength, 1, "8bit");
        $data = mb_substr($data, $ivLength + 1, null, "8bit");

        if (!in_array($serialized, ["Y", "N"])) {
            throw new EncryptionException("Invalid encrypted data (bad serialization flag)");
        }

        $decrypted = openssl_decrypt($data, $algorithm, $this->key(), 0, $iv);

        if (false === $decrypted) {
            throw new EncryptionException("Unable to decrypt data");
        }

        self::scrubString($data);

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
