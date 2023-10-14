<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

/** Trait used by several tests to provide the cipher methods that OpenSSL supports. */
trait ProvidesOpenSslUnsupportedAlgorithms
{
    public static function openSslUnsupportedAlgorithms(): iterable
    {
        yield "empty" => [""];

        if (!function_exists('openssl_get_cipher_methods')) {
            self::fail("OpenSSL extension doesn't appear to be loaded.");
        }

        $algorithms = openssl_get_cipher_methods();

        foreach (
            [
                "nonsense", "this-method-is-not-available", "something-else", "aes-127-cbc", "bluefish", "foo", "bar",
                "7", " ", "-",
            ] as $algorithm) {
            if (in_array($algorithm, $algorithms)) {
                # only test with algorithms known to be invalid
                continue;
            }

            yield $algorithm => [$algorithm];
        }
    }
}
