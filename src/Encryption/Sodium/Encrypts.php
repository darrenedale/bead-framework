<?php

declare(strict_types=1);

namespace Bead\Encryption\Sodium;

use Bead\Encryption\SerializationMode;
use Bead\Exceptions\EncryptionException;
use SodiumException;

/**
 * Trait to encrypt data using Sodium.
 */
trait Encrypts
{
    abstract private function key(): string;

    abstract private function randomBytes(int $len): string;

    /**
     * @throws EncryptionException if:
     * - Sodium throws during encryption; or
     * - a cryptographically-secure source of randomness is not available; or
     * - an invalid serialization mode is provided.
     */
    public function encrypt(mixed $data, int $serializationMode = SerializationMode::Auto): string
    {
        $serialized = "N";

        switch ($serializationMode) {
            case SerializationMode::Auto:
                if (!is_string($data)) {
                    $serialized = "Y";
                    $data = serialize($data);
                }
                break;

            case SerializationMode::On:
                $serialized = "Y";
                $data = serialize($data);
                break;

            case SerializationMode::Off:
                break;

            default:
                throw new EncryptionException("Invalid serialization mode");
        };

        $nonce = $this->randomBytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $encrypted = sodium_bin2base64(
                $nonce .
                $serialized .
                sodium_crypto_secretbox($data, $nonce, $this->key()),
                SODIUM_BASE64_VARIANT_ORIGINAL
            );
        } catch (SodiumException $err) {
            throw new EncryptionException("Exception encrypting data: {$err->getMessage()}", previous: $err);
        }

        sodium_memzero($data);
        sodium_memzero($serialized);
        return $encrypted;
    }
}
