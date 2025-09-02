<?php

declare(strict_types=1);

namespace BeadTests\Web\Responses;

use Bead\Testing\XRay;
use Bead\Web\Header;
use Bead\Web\Responses\SendsHeaders;
use BeadTests\Framework\TestCase;

final class SendsHeadersTest extends TestCase
{
    public const TestHeaders = [
        "content-disposition" => "download; filename=\"file.txt\"",
        "x-custom-header" => "bead-framework",
    ];

    /** Helper to create a new instance of a class that imports the trait under test. */
    private static function createInstance(): object
    {
        return new class
        {
            use SendsHeaders;

            public function contentType(): string
            {
                return "text/plain";
            }

            public function headers(): array
            {
                $headers = SendsHeadersTest::TestHeaders;
                array_walk($headers, static fn (string &$value, string $key) => $value = new Header($key, $value));
                return $headers;
            }
        };
    }

    /** Ensure we send the expected headers. */
    public function testSendHeaders(): void
    {
        $expectedHeaders = array_map(
            fn (string $key, string $value): string => "{$key}: {$value}",
            array_keys(self::TestHeaders),
            array_values(self::TestHeaders)
        );

        $expectedHeaders[] = "Content-Type: text/plain";
        $test = $this;

        $this->mockFunction(
            "header",
            function (string $header, bool $replace) use (&$expectedHeaders, $test) {
                if (str_starts_with($header, "Content-Type")) {
                    $test->assertTrue($replace);
                } else {
                    $test->assertFalse($replace);
                }

                $idx = array_search($header, $expectedHeaders);
                $test->assertIsInt($idx, "Unexpected header '{$header}' generated.");
                array_splice($expectedHeaders, $idx, 1);
            }
        );

        $instance = new XRay(self::createInstance());
        $instance->sendHeaders();
        self::assertEmpty($expectedHeaders, "Not all expected headers were generated.");
    }
}
