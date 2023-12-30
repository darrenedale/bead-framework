<?php

declare(strict_types=1);

namespace BeadTests\Responses;

use Bead\Responses\HasDefaultReasonPhrase;
use BeadTests\Framework\TestCase;

class HasDefaultReasonPhraseTest extends TestCase
{
    /** Create an anonymous object that imports the trait under test. */
    private static function createInstance(int $statusCode): object
    {
        return new class ($statusCode)
        {
            use HasDefaultReasonPhrase;

            private int $statusCode;

            public function __construct(int $statusCode)
            {
                $this->statusCode = $statusCode;
            }

            public function statusCode(): int
            {
                return $this->statusCode;
            }
        };
    }

    public static function dataForTestReasonPhrase1(): iterable
    {
        yield "status-100" => [100, "Continue",];
        yield "status-101" => [101, "Switching Protocols",];
        yield "status-102" => [102, "Processing",];
        yield "status-103" => [103, "Early Hints",];

        yield "status-200" => [200, "OK",];
        yield "status-201" => [201, "Created",];
        yield "status-202" => [202, "Accepted",];
        yield "status-203" => [203, "Non-Authoritative Information",];
        yield "status-204" => [204, "No Content",];
        yield "status-205" => [205, "Reset Content",];
        yield "status-206" => [206, "Partial Content",];
        yield "status-207" => [207, "Multi-Status",];
        yield "status-208" => [208, "Already Reported",];

        yield "status-226" => [226, "IM Used",];

        yield "status-300" => [300, "Multiple Choices",];
        yield "status-301" => [301, "Moved Permanently",];
        yield "status-302" => [302, "Found",];
        yield "status-303" => [303, "See Other",];
        yield "status-304" => [304, "Not Modified",];
        yield "status-305" => [305, "Use Proxy",];
        yield "status-307" => [307, "Temporary Redirect",];
        yield "status-308" => [308, "Permanent Redirect",];

        yield "status-400" => [400, "Bad Request",];
        yield "status-401" => [401, "Unauthorized",];
        yield "status-402" => [402, "Payment Required",];
        yield "status-403" => [403, "Forbidden",];
        yield "status-404" => [404, "Not Found",];
        yield "status-405" => [405, "Method Not Allowed",];
        yield "status-406" => [406, "Not Acceptable",];
        yield "status-407" => [407, "Proxy Authentication Required",];
        yield "status-408" => [408, "Request Timeout",];
        yield "status-409" => [409, "Conflict",];
        yield "status-410" => [410, "Gone",];
        yield "status-411" => [411, "Length Required",];
        yield "status-412" => [412, "Precondition Failed",];
        yield "status-413" => [413, "Content Too Large",];
        yield "status-414" => [414, "URI Too Long",];
        yield "status-415" => [415, "Unsupported Media Type",];
        yield "status-416" => [416, "Range Not Satisfiable",];
        yield "status-417" => [417, "Expectation Failed",];
        yield "status-418" => [418, "I'm a teapot",];

        yield "status-421" => [421, "Misdirected Request",];
        yield "status-422" => [422, "Unprocessable Content",];
        yield "status-423" => [423, "Locked",];
        yield "status-424" => [424, "Failed Dependency",];
        yield "status-425" => [425, "Too Early",];
        yield "status-426" => [426, "Upgrade Required",];

        yield "status-428" => [428, "Precondition Required",];
        yield "status-429" => [429, "Too Many Requests",];

        yield "status-431" => [431, "Request Header Fields Too Large",];

        yield "status-451" => [451, "Unavailable For Legal Reasons",];

        yield "status-500" => [500, "Internal Server Error",];
        yield "status-501" => [501, "Not Implemented",];
        yield "status-502" => [502, "Bad Gateway",];
        yield "status-503" => [503, "Service Unavailable",];
        yield "status-504" => [504, "Gateway Timeout",];
        yield "status-505" => [505, "HTTP Version Not Supported",];
        yield "status-506" => [506, "Variant Also Negotiates",];
        yield "status-507" => [507, "Insufficient Storage",];
        yield "status-508" => [508, "Loop Detected",];

        yield "status-510" => [510, "Not Extended",];
        yield "status-511" => [511, "Network Authentication Required",];
    }

    /**
     * Ensure we get the expected reason phrases for defined HTTP status codes.
     *
     * @dataProvider dataForTestReasonPhrase1
     */
    public function testReasonPhrase1(int $statusCode, string $expected): void
    {
        self::assertEquals($expected, self::createInstance($statusCode)->reasonPhrase());
    }

    public static function dataForTestReasonPhrase2(): iterable
    {
        for ($statusCode = 0; $statusCode < 100; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        for ($statusCode = 104; $statusCode < 200; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        for ($statusCode = 209; $statusCode < 226; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        for ($statusCode = 227; $statusCode < 300; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        for ($statusCode = 309; $statusCode < 400; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        for ($statusCode = 419; $statusCode < 421; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        yield "427" => [427,];
        yield "430" => [430,];

        for ($statusCode = 432; $statusCode < 451; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        for ($statusCode = 452; $statusCode < 500; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }

        yield "509" => [509,];

        for ($statusCode = 512; $statusCode < 600; ++$statusCode) {
            yield (string) $statusCode => [$statusCode,];
        }
    }

    /**
     * Ensure "Unknown" is returned for unknown status codes.
     *
     * @dataProvider dataForTestReasonPhrase2
     */
    public function testReasonPhrase2(int $statusCode): void
    {
        self::assertEquals("Unknown", self::createInstance($statusCode)->reasonPhrase());
    }
}
