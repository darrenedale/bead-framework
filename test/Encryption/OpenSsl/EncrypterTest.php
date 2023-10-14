<?php

declare(strict_types=1);

namespace BeadTests\Encryption\OpenSsl;

use Bead\Encryption\SerializationMode;
use Bead\Encryption\OpenSsl\Encrypter;
use Bead\Exceptions\EncryptionException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class EncrypterTest extends TestCase
{
    use ProvidesOpenSslSupportedAlgorithms;
    use ProvidesOpenSslUnsupportedAlgorithms;

    private const EncryptionKey = "-some-insecure-key-insecure-some";

    public static function dataForTestConstructor1(): iterable
    {
        yield from self::openSslSupportedAlgorithms();
    }

    /**
     * Ensure constructor sets algorithm and key as expected.
     *
     * @dataProvider dataForTestConstructor1
     */
    public function testConstructor1(string $algorithm): void
	{
		$crypter = new Encrypter($algorithm, self::EncryptionKey);
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
	public function testConstructor2(string $key): void
	{
		// get the first supported algorithm (fails test if there are none)
		foreach (self::openSslSupportedAlgorithms() as $algorithm) {
			// data provider provides array or args - algorithm is the first (and only) arg in each data set
			$algorithm = $algorithm[0];
			break;
		}

		self::expectException(EncryptionException::class);
		self::expectExceptionMessage("Invalid encryption key");
		new Encrypter($algorithm, $key);
	}

    public static function dataForTestConstructor3(): iterable
    {
		yield from self::openSslUnsupportedAlgorithms();
    }

    /**
	 * Ensure constructor throws with unsupported algorithms.
	 *
	 * @dataProvider dataForTestConstructor3
	 */
	public function testConstructor3(string $algorithm): void
	{
		self::expectException(EncryptionException::class);
		self::expectExceptionMessage("Cipher algorithm '{$algorithm}' is not supported");
		new Encrypter($algorithm, self::EncryptionKey);
	}
}
