<?php

namespace BeadTests\Email;

use Bead\Email\HasHeaders;
use Bead\Email\Header;
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
    private $instance;

    public function setUp(): void
    {
        parent::setUp();
        $this->instance = new class {
            use HasHeaders;
        };

        foreach (self::TestHeaders as $name => $value) {
            $this->instance->addHeader($name, $value);
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

        foreach ($expected as $expectedHeader) {
            $idx = 0;

            foreach ($actual as $actualHeader) {
                if ($expectedHeader->name() === $actualHeader->name() && $expectedHeader->value() === $actualHeader->value()) {
                    array_splice($actual, $idx, 1);
                    continue 2;
                }

                ++$idx;
            }

            self::fail("Expected header {$expectedHeader} not found in array.");
        }

        if (!empty($actual)) {
            self::fail(count($actual) . " unexpected headers found in array.");
        }
    }

    /** Ensure we can fetch all the headers. */
    public function testHeaders(): void
    {
        self::assertEqualsCanonicalizing(
            self::headersFromArray(self::TestHeaders),
            $this->instance->headers()
        );
    }

    /** Ensure we can retrieve a named header. */
    public function testHeaderByName(): void
    {
        $actual = $this->instance->headerByName("header-name-2");
        self::assertInstanceOf(Header::class, $actual);
        self::assertEquals("header-name-2", $actual->name());
        self::assertEquals("header-value-2", $actual->value());
    }

    /** Ensure asking for a named header that does not exist returns null. */
    public function testHeaderByNameWithNonExistent(): void
    {
        self::assertNull($this->instance->headerByName("header-name-5"));
    }

    /** Ensure we can successfully parse a line and add a header. */
    public function testAddHeaderLine(): void
    {
        $count = count($this->instance->headers());
        $this->instance->addHeaderLine("header-name-5: header-value-5");
        self::assertCount($count + 1, $this->instance->headers());
        $actual = $this->instance->headerByName("header-name-5");
        self::assertInstanceOf(Header::class, $actual);
        self::assertEquals("header-name-5", $actual->name());
        self::assertEquals("header-value-5", $actual->value());
    }

    /** Ensure we can successfully parse a header line that is validly spread over multiple lines. */
    public function testAddHeaderLineContinuation(): void
    {
        $count = count($this->instance->headers());
        $this->instance->addHeaderLine("header-name-5: header-value-\n\t5");
        self::assertCount($count + 1, $this->instance->headers());
        $actual = $this->instance->headerByName("header-name-5");
        self::assertInstanceOf(Header::class, $actual);
        self::assertEquals("header-name-5", $actual->name());
        self::assertEquals("header-value-\n\t5", $actual->value());
    }

    /** Ensure addHeaderLine() throws when given a header line with no name-value separator. */
    public function testAddHeaderLineNoSeparator(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Ill-formed header line \"header-name-5 header-value-5\".");
        $this->instance->addHeaderLine("header-name-5 header-value-5");
    }

    /** Ensure addHeaderLine() throws when given a header line with no name-value separator. */
    public function testAddHeaderLineEmpty(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Empty header line added.");
        $this->instance->addHeaderLine("");
    }

    /** Ensure addHeaderLine() throws when given a header line with no name-value separator. */
    public function testAddHeaderLineMultiple(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Header line contains more than one header.");
        $this->instance->addHeaderLine("header-name-5: header-value-5\nheader-name-6: header-value-6");
    }

    /** Ensure we can clear all the headers. */
    public function testClearHeaders(): void
    {
        self::assertGreaterThan(0, count($this->instance->headers()));
        $this->instance->clearHeaders();
        self::assertEquals([], $this->instance->headers());
    }

    /** Ensure we can remove a named header. */
    public function testRemoveHeader(): void
    {
        self::assertInstanceOf(Header::class, $this->instance->headerByName("header-name-2"));
        $this->instance->removeHeader("header-name-2");
        self::assertNull($this->instance->headerByName("header-name-2"));

        self::assertEqualHeaders(
            self::headersFromArray(array_filter(
                self::TestHeaders,
                fn(string $header): bool => "header-name-2" !== $header,
                ARRAY_FILTER_USE_KEY
            )),
            $this->instance->headers()
        );
    }

    /** Ensure removing a named header removes all instances of that header. */
    public function testRemoveHeaderMultiple(): void
    {
        $this->instance->addHeader("header-name-2", "header-value-5");
        self::assertCount(2, $this->instance->headerValues("header-name-2"));
        $this->instance->removeHeader("header-name-2");
        self::assertNull($this->instance->headerByName("header-name-2"));

        self::assertEqualHeaders(
            self::headersFromArray(array_filter(
                self::TestHeaders,
                fn(string $header): bool => "header-name-2" !== $header,
                ARRAY_FILTER_USE_KEY
            )),
            $this->instance->headers()
        );
    }

    /** Ensure we get the expected set of values for a named header. */
    public function testHeaderValues(): void
    {
        $this->instance->addHeader(new Header("header-name-2", "header-value-5"));
        self::assertEquals(["header-value-2", "header-value-5",], $this->instance->headerValues("header-name-2"));
    }

    /** Ensure an empty array is returned when asking for values for a header that doesn't exist. */
    public function testHeaderValuesNonExistent(): void
    {
        self::assertEquals([], $this->instance->headerValues("header-name-5"));
    }

    /** Ensure we can add a header. */
    public function testAddHeader(): void
    {
        $count = count($this->instance->headers());
        $header = new Header("header-name-5", "header-value-5");
        $this->instance->addHeader($header);
        self::assertCount($count + 1, $this->instance->headers());
        self::assertSame($header, $this->instance->headerByName("header-name-5"));
    }
}
