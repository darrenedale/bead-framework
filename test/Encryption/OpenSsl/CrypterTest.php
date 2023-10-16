<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Encryption\SerializationMode;
use Bead\Encryption\OpenSsl\Crypter;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class CrypterTest extends TestCase
{
    use ProvidesOpenSslSupportedAlgorithms;

    private const EncryptionKey = "-some-insecure-key-insecure-some";

    public static function dataForTestConstructor1(): iterable
    {
        if (!function_exists("openssl_get_cipher_methods")) {
            self::fail("OpenSSL extension doesn't appear to be loaded.");
        }

        yield from self::openSslSupportedAlgorithms();
    }

    /** @dataProvider dataForTestConstructor1 */
    public function testConstructor1(string $algorithm): void
    {
        $crypter = new Crypter($algorithm, self::EncryptionKey);
        self::assertEquals(self::EncryptionKey, (new XRay($crypter))->key());
        self::assertEquals($algorithm, $crypter->algorithm());
    }

    public static function dataForTestConstructor2(): iterable
    {
        yield "empty" => [""];
        yield "marginally too short" => ["some-insecure-key-insec"];
    }

    /**
     * Ensure constructor throws with a keys that are not long enough.
     *
     * @dataProvider dataForTestConstructor2
     */
    public function testConstructorThrows2(string $key): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Invalid encryption key");
        new Crypter("aes-256-gcm", $key);
    }

    public static function dataForTestConstructor3(): iterable
    {
        yield "empty" => [""];

        if (!function_exists("openssl_get_cipher_methods")) {
            self::fail("OpenSSL extension doesn't appear to be loaded.");
        }

        $algorithms = openssl_get_cipher_methods();

        foreach (
            [
                "nonsense", "this-method-is-not-available", "something-else", "aes-127-cbc", "bluefish", "foo", "bar",
                "7", " ", "-",
            ] as $algorithm
        ) {
            if (in_array($algorithm, $algorithms)) {
                // only test with algorithms known to be invalid
                continue;
            }

            yield $algorithm => [$algorithm];
        }
    }

    /**
     * Ensure constructor throws with a keys that are not long enough.
     *
     * @dataProvider dataForTestConstructor3
     */
    public function testConstructor3(string $algorithm): void
    {
        self::expectException(EncryptionException::class);
        self::expectExceptionMessage("Cipher algorithm '{$algorithm}' is not supported");
        new Crypter($algorithm, self::EncryptionKey);
    }
}
