<?php

namespace Equit\Test;

use Equit\Exceptions\UriSignerException;
use Equit\UriSigner;
use PHPUnit\Framework\TestCase;

class UriSignerTest extends TestCase
{
	private const Secret = "yhkja7hbvlkajsdu6fb";
	private const Parameters = [
		"id" => 1,
		"expires" => "2022-10-31T16:00:00UTC"
	];

	private UriSigner $m_signer;

	public function setUp(): void
	{
		$this->m_signer = (new UriSigner())->usingSecret(self::Secret)->withParameters(self::Parameters);
	}

	public function tearDown(): void
	{
		unset($this->m_signer);
	}

	public function testDefaultConstructor(): void
	{
		$signer = new UriSigner();
		$this->assertEquals(UriSigner::DefaultAlgorithm, $signer->algorithm());
		$this->assertEquals("", $signer->secret());
		$this->assertIsArray($signer->parameters());
		$this->assertEmpty($signer->parameters());
	}

	public function testConstructorWithAlgorithm(): void
	{
		$signer = new UriSigner("md5");
		$this->assertEquals("md5", $signer->algorithm());
		$this->assertEquals("", $signer->secret());
		$this->assertIsArray($signer->parameters());
		$this->assertEmpty($signer->parameters());
	}

	public function testConstructorWithUnsupportedAlgorithm(): void
	{
		$this->expectException(UriSignerException::class);
		new UriSigner("_0987fads089yhn 2q37&^%&*%^&");
	}

	public function testSign(): void
	{
		$expected = "https://bead.framework/protected/uri?id=1&expires=2022-10-31T16%3A00%3A00UTC&signature=88d42152ae89e49bfda93917f983e5abfd24bafe";
		$actual = $this->m_signer->sign("https://bead.framework/protected/uri");
		$this->assertEquals($expected, $actual);
	}

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

	public function testSecret(): void
	{
		$this->assertEquals(self::Secret, $this->m_signer->secret());
	}

	public function testWithParameters(): void
	{
		$parameters = [
			"id" => 2,
			"expires" => "2022-11-30T18:01:01UTC",
		];

		$this->assertNotEqualsCanonicalizing($parameters, $this->m_signer->parameters());
		$this->assertEqualsCanonicalizing(self::Parameters, $this->m_signer->parameters());
		$actual = $this->m_signer->withParameters($parameters);
		$this->assertInstanceOf(UriSigner::class, $actual);
		$this->assertNotSame($this->m_signer, $actual);
		$this->assertEqualsCanonicalizing(self::Parameters, $this->m_signer->parameters());
		$this->assertEqualsCanonicalizing($parameters, $actual->parameters());
	}

	public function testAlgorithm(): void
	{
		$this->assertEquals(UriSigner::DefaultAlgorithm, $this->m_signer->algorithm());

		foreach (hash_hmac_algos() as $algo) {
			$this->m_signer->setAlgorithm($algo);
			$this->assertEquals($algo, $this->m_signer->algorithm());
		}
	}

	/**
	 * Data provider for testParameters.
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestParameters(): iterable
	{
		yield from [
			"typical" => [
				[
					"id" => 2,
					"expires" => "2022-11-30T18:01:01UTC",
				],
			],
			"empty" => [
				[],
			],
		];
	}

	/**
	 * @dataProvider dataForTestParameters
	 *
	 * @param array $parameters The test parameters.
	 */
	public function testParameters(array $parameters): void
	{
		$this->assertEquals(self::Parameters, $this->m_signer->parameters());
		$signer = $this->m_signer->withParameters($parameters);
		$this->assertEqualsCanonicalizing($parameters, $signer->parameters());
	}

	public function testVerify(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri");
		$this->assertTrue($this->m_signer->verify($signed));
	}

	public function testVerifyRejects(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri");
		$this->assertFalse($this->m_signer->verify("{$signed}x"));
		$this->assertFalse($this->m_signer->verify(substr($signed, 0, -1)));
	}

	public function testVerifyRejectsWitReorderedParameters(): void
	{
		$signed = $this->m_signer->sign("http://bead.framework/protected/uri");
		$signature = substr($signed, strpos($signed, "&signature=") + 11);

		$actual = "http://bead.framework/protected/uri?expires=" .
			urlencode(self::Parameters["expires"]) .
			"&id=" . urlencode(self::Parameters["id"]) .
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
