<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\HasDefaultReasonPhrase;
use Bead\Responses\NaivelySendsContent;
use BeadTests\Framework\TestCase;

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

    /** Create an anonymous object that imports the trait under test. */
    private static function createInstance(): object
    {
        return new class
        {
            use HasDefaultReasonPhrase;
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
    public function testSend1(): void
    {
        $expectedHeaders = array_map(
            fn (string $key, string $value): string => "{$key}: {$value}",
            array_keys(self::TestHeaders),
            array_values(self::TestHeaders)
        );

        $expectedHeaders[] = "HTTP/1.1 200 OK";
        $expectedHeaders[] = "content-type: " . self::TestContentType;
        $test = $this;

        $this->mockFunction(
            "header",
            function (string $header, bool $replace = true) use (&$expectedHeaders, $test) {
                $test->assertTrue($replace);
                $idx = array_search($header, $expectedHeaders);
                $test->assertIsInt($idx, "Unexpected header '{$header}' generated.");
                array_splice($expectedHeaders, $idx, 1);
            }
        );

        ob_start();
        self::createInstance()->send();
        self::assertEquals(self::TestContent, ob_get_contents());
        ob_end_clean();
        self::assertEmpty($expectedHeaders, "Not all expected headers were generated.");
    }
}
