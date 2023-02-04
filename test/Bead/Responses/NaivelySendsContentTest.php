<?php

declare(strict_types=1);

namespace Bead\Responses;

use PHPUnit\Framework\TestCase;

final class NaivelySendsContentTest extends TestCase
{
	/** @var string The expected content of the response. */
	public const TestContent = "The test content";

	/** @var string THe expected content-type header for the response. */
	public const TestContentType = "text/plain";

	/** @var array<string,string> The expected custom headers for the response. */
	public const TestHeaders = [
		"x-custom-header" => "bead-framework",
	];

	/** @var int The expected HTTP status code for the response. */
	public const TestStatusCode = 200;

	public function tearDown(): void
	{
		if (uopz_get_return('header')) {
			uopz_unset_return('header');
		}

		if (uopz_get_return('http_response_code')) {
			uopz_unset_return('http_response_code');
		}
	}

	/** Create an anonymous object that imports the trait under test. */
	private function createInstance(): mixed
	{
		return new class
		{
			use NaivelySendsContent;

			public function statusCode(): int
			{
				return NaivelySendsContentTest::TestStatusCode;
			}

			public function headers(): array
			{
				return NaivelySendsContentTest::TestHeaders;
			}

			public function contentType(): string
			{
				return NaivelySendsContentTest::TestContentType;
			}

			public function content(): string
			{
				return NaivelySendsContentTest::TestContent;
			}
		};
	}

	/** Ensure send() provides the expected status code, headers and content. */
	public function testSend(): void
	{
		$expectedHeaders = array_map(
			fn (string $key, string $value): string => "{$key}: {$value}",
			array_keys(self::TestHeaders),
			array_values(self::TestHeaders)
		);

		$expectedHeaders[] = "content-type: " . self::TestContentType;
		$test = $this;

		uopz_set_return(
			'header',
			function (string $header, bool $replace) use (&$expectedHeaders, $test)
			{
				$test->assertTrue($replace);
				$idx = array_search($header, $expectedHeaders);
				$test->assertIsInt($idx, "Unexpected header '{$header}' generated.");
				array_splice($expectedHeaders, $idx, 1);
			},
			true
		);

		$httpResponseCodeCalled = 0;

		uopz_set_return(
			'http_response_code',
			function (int $code) use (&$httpResponseCodeCalled, $test)
			{
				++$httpResponseCodeCalled;
				$test->assertEquals(NaivelySendsContentTest::TestStatusCode, $code);
			},
			true
		);

		ob_start();
		$this->createInstance()->send();
		self::assertEquals(1, $httpResponseCodeCalled);
		self::assertEquals(self::TestContent, ob_get_contents());
		ob_end_clean();
		$this->assertEmpty($expectedHeaders, "Not all expected headers were generated.");
	}
}