<?php

namespace BeadTests;

use DateTime;
use DateTimeImmutable;
use Bead\Exceptions\UriSignerException;
use Bead\UriSigner;
use BeadTests\Framework\TestCase;

class UriSignerTest extends TestCase
{
    /** @var string The secret to use in the test signer. */
    private const Secret = "yhkja7hbvlkajsdu6fb";

    /** @var array The test parameters to use for URIs to be signed. */
    private const Parameters = [
        "id" => 1,
        "token" => "XF12jka8hbyHIofu6dSauioCHUIsfui754g",
    ];

    /** @var array The expiry timestamp to use for testing. */
    private const ExpiresTimestamp = 1667232000;

    /** @var array The expiry DateTime to use for testing - tests require this to be the same as the timestamp above. */
    private const ExpiresDateTime = "2022-10-31T16:00:00UTC";

    /** @var UriSigner The test signer. */
    private UriSigner $m_signer;

    public function setUp(): void
    {
        $this->m_signer = (new UriSigner())->usingSecret(self::Secret);
    }

    public function tearDown(): void
    {
        unset($this->m_signer);
    }

    /** Ensure the default constructor initialises the signer with the expected state. */
    public function testConstructor1(): void
    {
        $signer = new UriSigner();
        self::assertEquals(UriSigner::DefaultAlgorithm, $signer->algorithm());
        self::assertEquals("", $signer->secret());
    }

    /** Ensure we can set a hashing algorithm in the constructor. */
    public function testConstructor2(): void
    {
        $signer = new UriSigner("md5");
        self::assertEquals("md5", $signer->algorithm());
        self::assertEquals("", $signer->secret());
    }

    /** Ensure setting an unsupported algorithm in the constructor throws. */
    public function testConstructor3(): void
    {
        $this->expectException(UriSignerException::class);
        new UriSigner("_0987fads089yhn 2q37&^%&*%^&");
    }

    /** Ensure we can set the hashing algorithm. */
    public function testSetAlgorithm1(): void
    {
        $algos = hash_hmac_algos();

        if (empty($algos)) {
            self::markTestSkipped("hash_hmac_algos() returned no supported algorithms - URI signing is not possible.");
        }

        foreach ($algos as $algo) {
            $this->m_signer->setAlgorithm($algo);
            self::assertEquals($algo, $this->m_signer->algorithm());
        }
    }

    /** Ensure we can retrieve the hashing algorithm. */
    public function testAlgorithm1(): void
    {
        self::assertEquals(UriSigner::DefaultAlgorithm, $this->m_signer->algorithm());

        foreach (hash_hmac_algos() as $algo) {
            $this->m_signer->setAlgorithm($algo);
            self::assertEquals($algo, $this->m_signer->algorithm());
        }
    }

    /** Ensure setting the secret is immutable. */
    public function testUsingSecret1(): void
    {
        self::assertNotEquals("some-other-secret", $this->m_signer->secret());
        self::assertEquals(self::Secret, $this->m_signer->secret());
        $actual = $this->m_signer->usingSecret("some-other-secret");
        self::assertInstanceOf(UriSigner::class, $actual);
        self::assertNotSame($this->m_signer, $actual);
        self::assertEquals(self::Secret, $this->m_signer->secret());
        self::assertEquals("some-other-secret", $actual->secret());
    }

    /** Ensure secret() returns the expected secret. */
    public function testSecret1(): void
    {
        self::assertEquals(self::Secret, $this->m_signer->secret());
        $signer = $this->m_signer->usingSecret("some-other-secret");
        self::assertEquals("some-other-secret", $signer->secret());
    }

    /** Ensure sign() produces the expected URIs when given timestamps and DateTime objects as expiry times. */
    public function testSign1(): void
    {
        $expected = "https://bead.framework/protected/uri?id=1&token=XF12jka8hbyHIofu6dSauioCHUIsfui754g&expires=1667232000&signature=850a5ede2b7af8a7354a443171aa67a90bddcbaf";
        $actual = $this->m_signer->sign("https://bead.framework/protected/uri", self::Parameters, self::ExpiresTimestamp);
        self::assertEquals($expected, $actual);
        $actual = $this->m_signer->sign("https://bead.framework/protected/uri", self::Parameters, new DateTime(self::ExpiresDateTime));
        self::assertEquals($expected, $actual);
        $actual = $this->m_signer->sign("https://bead.framework/protected/uri", self::Parameters, new DateTimeImmutable(self::ExpiresDateTime));
        self::assertEquals($expected, $actual);
    }

    /** Ensure verify() can correctly verify a signature. */
    public function testVerify1(): void
    {
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], PHP_INT_MAX);
        self::assertTrue($this->m_signer->verify($signed));
    }

    /** Ensure specifying a verification time with a timestamp verifies as expected. */
    public function testVerify2(): void
    {
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp);
        self::assertTrue($this->m_signer->verify($signed, self::ExpiresTimestamp - 1));
    }

    /** Ensure specifying a verification time with a DateTime object verifies as expected. */
    public function testVerify3(): void
    {
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp + (60 * 60 * 24 * 7));
        self::assertTrue($this->m_signer->verify($signed, new DateTime(self::ExpiresDateTime)));
    }

    /** Ensure mismatching signatures result in verification failure. */
    public function testVerify4(): void
    {
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], PHP_INT_MAX);
        self::assertFalse($this->m_signer->verify("{$signed}x"));
        self::assertFalse($this->m_signer->verify(substr($signed, 0, -1)));
    }

    /** Ensure we can specify a verification test time using a timestamp and get a verification fail when expected. */
    public function testVerify5(): void
    {
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp);
        self::assertFalse($this->m_signer->verify($signed, self::ExpiresTimestamp + 1));
    }

    /** Ensure we can specify a verification test time using a DateTime object and get a verification fail when expected. */
    public function testVerify6(): void
    {
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp - 1);
        self::assertFalse($this->m_signer->verify($signed, new DateTime(self::ExpiresDateTime)));
    }

    /** Ensure expired signed URIs are rejected when the time to verify at is now. */
    public function testVerify7(): void
    {
        $now = time() - 1;
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], $now);
        self::assertFalse($this->m_signer->verify($signed));
    }

    /**
     * Ensure changing the expiry URI parameter renders the URI invalid.
     *
     * Third parties must not be able to render expired signed URIs valid by chaning the timestamp - doing so should
     * change the expected signature, causing the URI to fail to verify.
     */
    public function testVerify8(): void
    {
        $expires = self::ExpiresTimestamp - 1;
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], $expires);
        $modifiedExpires = self::ExpiresTimestamp + 1;
        $badSigned = str_replace("expires={$expires}", "expires={$modifiedExpires}", $signed);
        self::assertNotEquals($signed, $badSigned);
        self::assertFalse($this->m_signer->verify($signed, self::ExpiresTimestamp));
    }

    /** Ensure signed URIs aren't verified when the order of the URI parameters has been changed. */
    public function testVerify9(): void
    {
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", self::Parameters, self::ExpiresTimestamp);
        $signature = substr($signed, strpos($signed, "&signature=") + 11);

        $actual = "http://bead.framework/protected/uri?token=" .
            urlencode(self::Parameters["token"]) .
            "&id=" . urlencode(self::Parameters["id"]) .
            "&expires=" . self::ExpiresTimestamp .
            "&signature={$signature}";

        // prove that the signed and the actual URIs are the same, bar the order of parameters
        $signedParams = explode("&", parse_url($signed, PHP_URL_QUERY));
        $actualParams = explode("&", parse_url($actual, PHP_URL_QUERY));
        self::assertEqualsCanonicalizing($signedParams, $actualParams);

        foreach ([PHP_URL_SCHEME, PHP_URL_HOST, PHP_URL_PORT, PHP_URL_PATH, PHP_URL_USER, PHP_URL_PASS,] as $component) {
            self::assertEquals(parse_url($signed, $component), parse_url($actual, $component));
        }

        self::assertNotEquals($signed, $actual);

        // prove that the different order causes verification to fail
        self::assertFalse($this->m_signer->verify($actual));
    }
}
