<?php

namespace BeadTests\Responses;

use Bead\Responses\SendsHeaders;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

final class SendsHeadersTest extends TestCase
{
    public const TestHeaders = [
        "content-disposition" => "download; filename=\"file.txt\"",
        "x-custom-header" => "bead-framework",
    ];

    /** Helper to create a new instance of a class that imports the trait under test. */
    private function createInstance(): mixed
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
                return SendsHeadersTest::TestHeaders;
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

        $expectedHeaders[] = "content-type: text/plain";
        $test = $this;

        $this->mockFunction(
            "header",
            function (string $header, bool $replace) use (&$expectedHeaders, $test) {
                $test->assertTrue($replace);
                $idx = array_search($header, $expectedHeaders);
                $test->assertIsInt($idx, "Unexpected header '{$header}' generated.");
                array_splice($expectedHeaders, $idx, 1);
            }
        );

        $instance = new XRay($this->createInstance());
        $instance->sendHeaders();
        self::assertEmpty($expectedHeaders, "Not all expected headers were generated.");
    }
}
