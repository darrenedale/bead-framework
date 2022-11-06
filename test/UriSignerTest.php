<?php

namespace Equit\Test;

use DateTime;
use DateTimeImmutable;
use Equit\Exceptions\UriSignerException;
use Equit\UriSigner;
use PHPUnit\Framework\TestCase;

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

    /**
     * Ensure the default constructor initialises the signer with the expected state.
     */
	public function testDefaultConstructor(): void
	{
		$signer = new UriSigner();
		$this->assertEquals(UriSigner::DefaultAlgorithm, $signer->algorithm());
		$this->assertEquals("", $signer->secret());
	}

    /**
     * Ensure we can set a hashing algorithm in the constructor.
     */
	public function testConstructorWithAlgorithm(): void
	{
		$signer = new UriSigner("md5");
		$this->assertEquals("md5", $signer->algorithm());
		$this->assertEquals("", $signer->secret());
	}

    /**
     * Ensure setting an unsupported algorithm in the constructor throws.
     */
	public function testConstructorWithUnsupportedAlgorithm(): void
	{
		$this->expectException(UriSignerException::class);
		new UriSigner("_0987fads089yhn 2q37&^%&*%^&");
	}

    /**
     * Ensure we can set the hashing algorithm.
     */
	public function testSetAlgorithm(): void
	{
		$algos = hash_hmac_algos();

		if (empty($algos)) {
			$this->markTestSkipped("hash_hmac_algos() returned no supported algorithms - URI signing is not possible.");
			return;
		}

		foreach ($algos as $algo) {
			$this->m_signer->setAlgorithm($algo);
			$this->assertEquals($algo, $this->m_signer->algorithm());
		}
	}

    /**
     * Ensure we can retrieve the hashing algorithm.
     */
    public function testAlgorithm(): void
    {
        $this->assertEquals(UriSigner::DefaultAlgorithm, $this->m_signer->algorithm());

        foreach (hash_hmac_algos() as $algo) {
            $this->m_signer->setAlgorithm($algo);
            $this->assertEquals($algo, $this->m_signer->algorithm());
        }
    }

    /**
     * Ensure setting the secret is immutable.
     */
	public function testUsingSecret(): void
	{
		$this->assertNotEquals("some-other-secret", $this->m_signer->secret());
		$this->assertEquals(self::Secret, $this->m_signer->secret());
		$actual = $this->m_signer->usingSecret("some-other-secret");
		$this->assertInstanceOf(UriSigner::class, $actual);
		$this->assertNotSame($this->m_signer, $actual);
		$this->assertEquals(self::Secret, $this->m_signer->secret());
		$this->assertEquals("some-other-secret", $actual->secret());
	}

    /**
     * Ensure secret() returns the expected secret.
     */
	public function testSecret(): void
	{
		$this->assertEquals(self::Secret, $this->m_signer->secret());
		$signer = $this->m_signer->usingSecret("some-other-secret");
		$this->assertEquals("some-other-secret", $signer->secret());
	}

    /**
     * Ensure sign() produces the expected URIs when given timestamps and DateTime objects as expiry times.
     */
    public function testSign(): void
    {
        $expected = "https://bead.framework/protected/uri?id=1&token=XF12jka8hbyHIofu6dSauioCHUIsfui754g&expires=1667232000&signature=850a5ede2b7af8a7354a443171aa67a90bddcbaf";
        $actual = $this->m_signer->sign("https://bead.framework/protected/uri", self::Parameters, self::ExpiresTimestamp);
        $this->assertEquals($expected, $actual);
        $actual = $this->m_signer->sign("https://bead.framework/protected/uri", self::Parameters, new DateTime(self::ExpiresDateTime));
        $this->assertEquals($expected, $actual);
        $actual = $this->m_signer->sign("https://bead.framework/protected/uri", self::Parameters, new DateTimeImmutable(self::ExpiresDateTime));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Ensure verify() can correctly verify a signature.
     */
	public function testVerify(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], PHP_INT_MAX);
		$this->assertTrue($this->m_signer->verify($signed));
	}

    /**
     * Ensure specifying a verification time with a timestamp verifies as expected.
     */
	public function testVerifyWithTimestamp(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp);
		$this->assertTrue($this->m_signer->verify($signed, self::ExpiresTimestamp - 1));
	}

    /**
     * Ensure specifying a verification time with a DateTime object verifies as expected.
     */
	public function testVerifyWithDateTime(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp + (60 * 60 * 24 * 7));
		$this->assertTrue($this->m_signer->verify($signed, new DateTime(self::ExpiresDateTime)));
	}

    /**
     * Ensure mismatching signatures result in verification failure.
     */
	public function testVerifyRejectsBadSignature(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], PHP_INT_MAX);
		$this->assertFalse($this->m_signer->verify("{$signed}x"));
		$this->assertFalse($this->m_signer->verify(substr($signed, 0, -1)));
	}

    /**
     * Ensure we can specify a verification test time using a timestamp and get a verification fail when expected.
     */
	public function testVerifyRejectsExpiredTimestamp(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp);
		$this->assertFalse($this->m_signer->verify($signed, self::ExpiresTimestamp + 1));
	}

    /**
     * Ensure we can specify a verification test time using a DateTime object and get a verification fail when expected.
     */
	public function testVerifyRejectsExpiredDateTime(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], self::ExpiresTimestamp - 1);
		$this->assertFalse($this->m_signer->verify($signed, new DateTime(self::ExpiresDateTime)));
	}

    /**
     * Ensure expired signed URIs are rejected when the time to verify at is now.
     */
	public function testVerifyRejectsExpiredNow(): void
	{
		$now = time() - 1;
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], $now);
		$this->assertFalse($this->m_signer->verify($signed));
	}

    /**
     * Ensure changing the expiry URI parameter renders the URI invalid.
     *
     * Third parties must not be able to render expired signed URIs valid by chaning the timestamp - doing so should
     * change the expected signature, causing the URI to fail to verify.
     */
    public function testVerifyRejectsAlteredTimestammp(): void
    {
        $expires = self::ExpiresTimestamp - 1;
        $signed = $this->m_signer->sign("http://bead.framework/protected/uri", [], $expires);
        $modifiedExpires = self::ExpiresTimestamp + 1;
        $badSigned = str_replace("expires={$expires}", "expires={$modifiedExpires}", $signed);
        $this->assertNotEquals($signed, $badSigned);
        $this->assertFalse($this->m_signer->verify($signed, self::ExpiresTimestamp));
    }

    /**
     * Ensure signed URIs aren't verified when the order of the URI parameters has been changed.
     */
	public function testVerifyRejectsWitReorderedParameters(): void
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
		$this->assertEqualsCanonicalizing($signedParams, $actualParams);

		foreach ([PHP_URL_SCHEME, PHP_URL_HOST, PHP_URL_PORT, PHP_URL_PATH, PHP_URL_USER, PHP_URL_PASS,] as $component) {
			$this->assertEquals(parse_url($signed, $component), parse_url($actual, $component));
		}

		$this->assertNotEquals($signed, $actual);

		// prove that the different order causes verification to fail
		$this->assertFalse($this->m_signer->verify($actual));
	}
}
