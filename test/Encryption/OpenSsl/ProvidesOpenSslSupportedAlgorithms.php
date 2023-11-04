<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

/** Trait used by several tests to provide the cipher methods that OpenSSL supports. */
trait ProvidesOpenSslSupportedAlgorithms
{
    public static function openSslSupportedAlgorithms(): iterable
    {
        if (!function_exists("openssl_get_cipher_methods")) {
            self::fail("OpenSSL extension doesn't appear to be loaded.");
        }

        $algorithms = openssl_get_cipher_methods();

        if (0 === count($algorithms)) {
            self::fail("No OpenSSL cipher methods supported.");
        }

        foreach ($algorithms as $algorithm) {
            yield $algorithm => [$algorithm];
        }
    }
}
