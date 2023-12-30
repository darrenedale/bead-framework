<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\SanitisesReasonPhrase;
use BeadTests\Framework\TestCase;

class SanitisesReasonPhraseTest extends TestCase
{
    /** Create an anonymous object that imports the trait under test. */
    private static function createInstance(string $reasonPhrase): object
    {
        return new class($reasonPhrase)
        {
            use SanitisesReasonPhrase;

            private string $reasonPhrase;

            public function __construct(string $reasonPhrase)
            {
                $this->reasonPhrase = $reasonPhrase;
            }

            public function reasonPhrase(): string
            {
                return $this->reasonPhrase;
            }
        };
    }

    public static function dataForTestSanitisedReasonPhrase1(): iterable
    {
        yield "empty" => ["", "",];
        yield "whitespace" => ["  ", "  ",];
        yield "whitesapce-cr" => [" \r ", "   ",];
        yield "whitesapce-lf" => [" \n ", "   ",];
        yield "whitesapce-crlf" => [" \r\n ", "    ",];
        yield "whitesapce-lfcr" => [" \n\r ", "    ",];
        yield "whitesapce-cr-whitespace-lf" => [" \r \n ", "     ",];
        yield "whitesapce-lf-whitespace-cr" => [" \n \r ", "     ",];
        yield "text-cr" => ["reason\r", "reason ",];
        yield "text-lf" => ["reason\n", "reason ",];
        yield "text-crlf" => ["reason\r\n", "reason  ",];
        yield "text-crlf-embedded" => ["rea\r\nson", "rea  son",];
    }

    /**
     * Ensure the reason phrase is sanitised as expected.
     *
     * @dataProvider dataForTestSanitisedReasonPhrase1
     */
    public function testSanitisedReasonPhrase1(string $reasonPhrase, string $expected): void
    {
        self::assertEquals($expected, self::createInstance($reasonPhrase)->sanitisedReasonPhrase());
    }
}
