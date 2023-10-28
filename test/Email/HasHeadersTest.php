<?php

namespace BeadTests\Email;

use Bead\Email\HasHeaders;
use Bead\Email\Header;
use Bead\Testing\StaticXRay;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use InvalidArgumentException;

class HasHeadersTest extends TestCase
{
    private const TestHeaders = [
        "header-name-1" => "header-value-1",
        "header-name-2" => "header-value-2",
        "header-name-3" => "header-value-3",
        "header-name-4" => "header-value-4",
    ];

    /** @var HasHeaders The instance under test. */
    private mixed $instance;

    public function setUp(): void
    {
        parent::setUp();
        $this->instance = new class {
            use HasHeaders;
        };

        foreach (self::TestHeaders as $name => $value) {
            $this->instance = $this->instance->withHeader($name, $value);
        }
    }

    public function tearDown(): void
    {
        unset ($this->instance);
        parent::tearDown();
    }

    /**
     * Build an array of Header objects from an array of key-value pairs.
     *
     * @param array<string,string> $headerData
     *
     * @return Header[]
     */
    private static function headersFromArray(array $headerData): array
    {
        $headers = [];

        foreach ($headerData as $name => $value) {
            $headers[] = new Header($name, $value);
        }

        return $headers;
    }

    /**
     * Assert that a set of headers matches an expected set.
     *
     * Order is not significant. Headers must match for name and value.
     *
     * @param Header[] $expected
     * @param Header[] $actual
     */
    private static function assertEqualHeaders(array $expected, array $actual): void
    {
        self::assertCount(count($expected), $actual);

        $map = fn (Header $header): array => [
            "name" => $header->name(),
            "value" => $header->value(),
            "parameters" => $header->parameters(),
        ];

        self::assertEqualsCanonicalizing(array_map($map, $expected), array_map($map, $actual));
    }

    /** Ensure we can fetch all the headers. */
    public function testHeaders1(): void
    {
        self::assertEqualHeaders(
            self::headersFromArray(self::TestHeaders),
            $this->instance->headers()
        );
    }

    /** Ensure we get an empty array when there are no headers added. */
    public function testHeaders2(): void
    {
        $headers = new class {
            use HasHeaders;
        };

        self::assertEquals([], $headers->headers());
    }

    /** Ensure we can retrieve a named header. */
    public function testHeader1(): void
    {
        $actual = $this->instance->header("header-name-2");
        self::assertInstanceOf(Header::class, $actual);
        self::assertEquals("header-name-2", $actual->name());
        self::assertEquals("header-value-2", $actual->value());
    }

    /** Ensure asking for a named header that does not exist returns null. */
    public function testHeader2(): void
    {
        self::assertNull($this->instance->header("header-name-5"));
    }

    /** Ensure we get an array of 1 Header when only one header matches. */
    public function testHeadersNamed1(): void
    {
        self::assertEqualHeaders(
            [
                new Header('header-name-1', "header-value-1"),
            ],
            $this->instance->headersNamed("header-name-1")
        );
    }

    /** Ensure we get all the headers when headers match. */
    public function testHeadersNamed2(): void
    {
        $this->instance = $this->instance->withHeader("header-name-1", "header-value-5");

        self::assertEqualHeaders(
            [
                new Header('header-name-1', "header-value-1"),
                new Header('header-name-1', "header-value-5"),
            ],
            $this->instance->headersNamed("header-name-1")
        );
    }

    /** Ensure we get an empty array when no headers match. */
    public function testHeadersNamed3(): void
    {
        self::assertEqualHeaders([], $this->instance->headersNamed("header-name-5"));
    }

    /** Ensure we can remove a named header. */
    public function testWithoutHeader1(): void
    {
        self::assertInstanceOf(Header::class, $this->instance->header("header-name-2"));
        $instance = $this->instance->withoutHeader("header-name-2");
        self::assertInstanceOf(Header::class, $this->instance->header("header-name-2"));
        self::assertNull($instance->header("header-name-2"));

        self::assertEqualHeaders(
            self::headersFromArray(array_filter(
                self::TestHeaders,
                fn(string $header): bool => "header-name-2" !== $header,
                ARRAY_FILTER_USE_KEY
            )),
            $instance->headers()
        );
    }

    /** Ensure removing a named header removes all instances of that header. */
    public function testWithoutHeader2(): void
    {
        $this->instance = $this->instance->withHeader("header-name-2", "header-value-5");
        self::assertCount(2, $this->instance->headerValues("header-name-2"));
        $instance = $this->instance->withoutHeader("header-name-2");
        self::assertNull($instance->header("header-name-2"));

        self::assertEqualHeaders(
            self::headersFromArray(array_filter(
                self::TestHeaders,
                fn(string $header): bool => "header-name-2" !== $header,
                ARRAY_FILTER_USE_KEY
            )),
            $instance->headers()
        );
    }

    /** Ensure we don't remove anything if the header isn't present. */
    public function testWithoutHeader3(): void
    {
        $instance = $this->instance->withoutHeader("header-name-5");
        self::assertEqualHeaders($instance->headers(), $this->instance->headers());
    }

    /** Ensure we can remove a matching Header. */
    public function testWithoutHeader4(): void
    {
        self::assertInstanceOf(Header::class, $this->instance->header("header-name-2"));
        $instance = $this->instance->withoutHeader(new Header("header-name-2", "header-value-2"));
        self::assertNull($instance->header("header-name-2"));

        self::assertEqualHeaders(
            self::headersFromArray(array_filter(
                self::TestHeaders,
                fn(string $header): bool => "header-name-2" !== $header,
                ARRAY_FILTER_USE_KEY
            )),
            $instance->headers()
        );
    }

    /** Ensure we can remove multiple matching Headers. */
    public function testWithoutHeader5(): void
    {
        $this->instance = $this->instance->withHeader("header-name-2", "header-value-2");
        self::assertCount(2, $this->instance->headersNamed("header-name-2"));
        $instance = $this->instance->withoutHeader(new Header("header-name-2", "header-value-2"));
        self::assertNull($instance->header("header-name-2"));

        self::assertEqualHeaders(
            self::headersFromArray(array_filter(
                self::TestHeaders,
                fn(string $header): bool => "header-name-2" !== $header,
                ARRAY_FILTER_USE_KEY
            )),
            $instance->headers()
        );
    }

    /** Ensure we don't remove any Headers when none match. */
    public function testWithoutHeader6(): void
    {
        $instance = $this->instance->withoutHeader(new Header("header-name-2", "header-value-3"));
        self::assertEqualHeaders($this->instance->headers(), $instance->headers());
    }

    /** Ensure we don't remove any Headers when parameters don't match. */
    public function testWithoutHeader7(): void
    {
        $instance = $this->instance->withoutHeader(new Header("header-name-2", "header-value-2", ["parameter-1" => "value-1",]));
        self::assertEqualHeaders($this->instance->headers(), $instance->headers());
    }

    /** Ensure we get the expected set of values for a named header. */
    public function testHeaderValues1(): void
    {
        $this->instance = $this->instance->withHeader(new Header("header-name-2", "header-value-5"));
        self::assertEquals(["header-value-2", "header-value-5",], $this->instance->headerValues("header-name-2"));
    }

    /** Ensure an empty array is returned when asking for values for a header that doesn't exist. */
    public function testHeaderValues2(): void
    {
        self::assertEquals([], $this->instance->headerValues("header-name-5"));
    }

    /** Ensure we can add a header. */
    public function testWithHeader(): void
    {
        $header = new Header("header-name-5", "header-value-5");
        $instance = $this->instance->withHeader($header);
        self::assertNotSame($this->instance, $instance);
        self::assertCount(count($this->instance->headers()) + 1, $instance->headers());
        self::assertSame($header, $instance->header("header-name-5"));
    }

    /** Ensure two sets of parameters match. */
    public function testParametersMatch1(): void
    {
        $headers = new StaticXRay(get_class($this->instance));

        self::assertTrue($headers->parametersMatch(
            [
                "parameter-1" => "value-1",
                "parameter-2" => "value-2",
            ],
            [
                "parameter-1" => "value-1",
                "parameter-2" => "value-2",
            ],
        ));
    }

    /** Ensure two sets of parameters match regardless of order. */
    public function testParametersMatch2(): void
    {
        $headers = new StaticXRay(get_class($this->instance));

        self::assertFalse($headers->parametersMatch(
            [
                "parameter-1" => "value-1",
                "parameter-2" => "value-2",
            ],
            [
                "parameter-2" => "value-2",
                "parameter-1" => "value-3",
            ],
        ));
    }

    /** Ensure two sets of parameters with mismatched keys don't match. */
    public function testParametersMatch3(): void
    {
        $headers = new StaticXRay(get_class($this->instance));

        self::assertFalse($headers->parametersMatch(
            [
                "parameter-1" => "value-1",
                "parameter-2" => "value-2",
            ],
            [
                "parameter-3" => "value-1",
                "parameter-2" => "value-2",
            ],
        ));
    }

    public static function dataForTestHeadersMatch1(): iterable
    {
        yield "header-matches-header" => [
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-1" => "value-1",
                    "parameter-2" => "value-2",
                ]
            ),
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-2" => "value-2",
                    "parameter-1" => "value-1",
                ]
            ),
            true,
        ];

        yield "header-name-mismatches-header" => [
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-1" => "value-1",
                    "parameter-2" => "value-2",
                ]
            ),
            new Header(
                "header-2",
                "value-1",
                [
                    "parameter-2" => "value-2",
                    "parameter-1" => "value-1",
                ]
            ),
            false,
        ];

        yield "header-value-mismatches-header" => [
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-1" => "value-1",
                    "parameter-2" => "value-2",
                ]
            ),
            new Header(
                "header-1",
                "value-2",
                [
                    "parameter-2" => "value-2",
                    "parameter-1" => "value-1",
                ]
            ),
            false,
        ];

        yield "header-parameter-name-mismatches-header" => [
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-1" => "value-1",
                    "parameter-2" => "value-2",
                ]
            ),
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-3" => "value-2",
                    "parameter-1" => "value-1",
                ]
            ),
            false,
        ];

        yield "header-parameter-value-mismatches-header" => [
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-1" => "value-1",
                    "parameter-2" => "value-2",
                ]
            ),
            new Header(
                "header-1",
                "value-1",
                [
                    "parameter-2" => "value-3",
                    "parameter-1" => "value-1",
                ]
            ),
            false,
        ];

        yield "header-matches-header-name" => [new Header("header-1", "value-1"), "header-1", true,];
        yield "header-mismatches-header-name" => [new Header("header-1", "value-1"), "header-2", false,];
    }

    /**
     * @dataProvider dataForTestHeadersMatch1
     *
     * @param Header $first The header to match against.
     * @param Header|string $second The header or name to match.
     * @param bool $expected Whether it should be a match.
     */
    public function testHeadersMatch1(Header $first, Header|string $second, bool $expected): void
    {
        $headers = new StaticXRay(get_class($this->instance));
        self::assertEquals($expected, $headers->headersMatch($first, $second));
    }


    /** Ensure a header can be added internally using setHeader() */
    public function testSetHeader1(): void
    {
        $instance = new XRay($this->instance);
        $expected = self::headersFromArray(self::TestHeaders);
        self::assertEqualHeaders($expected, $this->instance->headers());
        $instance->setHeader("added-header-3", "added-value-3", ["added-parameter-1" => "added-value-1",]);
        $expected[] = new Header("added-header-3", "added-value-3", ["added-parameter-1" => "added-value-1",]);
        self::assertEqualHeaders($expected, $this->instance->headers());
    }


    /** Ensure an existing header can be set internally using setHeader() */
    public function testSetHeader2(): void
    {
        $instance = new XRay($this->instance);
        $expected = self::headersFromArray(self::TestHeaders);
        self::assertEqualHeaders($expected, $this->instance->headers());
        $instance->setHeader("header-name-3", "new-value-3", ["new-parameter-1" => "new-value-1",]);
        $expected = array_filter($expected, fn (Header $header): bool => $header->name() !== "header-name-3");
        $expected[] = new Header("header-name-3", "new-value-3", ["new-parameter-1" => "new-value-1",]);
        self::assertEqualHeaders($expected, $this->instance->headers());
    }
}
