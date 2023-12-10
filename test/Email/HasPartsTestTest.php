<?php

declare(strict_types=1);

namespace BeadTests\Email;

use Bead\Email\HasParts;
use Bead\Email\Mime;
use Bead\Email\Part;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

class HasPartsTestTest extends TestCase
{
    private const TestParts = [
        ["Plain text content", "text/plain", "quoted-printable",],
        ["Custom bead-framework content", "application/x-bead-framework", "x-bead-encoding",],
    ];

    /** @var HasParts The instance under test. */
    private mixed $instance;

    public function setUp(): void
    {
        parent::setUp();
        $this->instance = new class {
            use HasParts;
        };

        foreach (self::TestParts as $part) {
            $this->instance = $this->instance->withPart(...$part);
        }
    }

    public function tearDown(): void
    {
        unset ($this->instance);
        parent::tearDown();
    }

    /** Ensure we can fetch all the parts. */
    public function testParts1(): void
    {
        $parts = $this->instance->parts();
        self::assertCount(2, $parts);

        $map = fn (Part $part): array => [
            $part->body(),
            $part->contentType(),
            $part->contentEncoding(),
        ];

        self::assertEqualsCanonicalizing(self::TestParts, array_map($map, $parts));
    }

    /** Ensure we get an empty array when there are no parts added. */
    public function testParts2(): void
    {
        $parts = new class {
            use HasParts;
        };

        self::assertEquals([], $parts->parts());
    }

    /** Ensure we get the expected part count. */
    public function testPartCount1(): void
    {
        self::assertEquals(2, $this->instance->partCount());
    }

    /** Ensure we get 0 when there are no parts. */
    public function testPartCount2(): void
    {
        $parts = new class {
            use HasParts;
        };

        self::assertEquals(0, $parts->partCount());
    }

    /** Ensure Mime::generateMultipartBoundary() is used to generate a multipart boundary. */
    public function testMultipartBoundary1(): void
    {
        $this->mockMethod(Mime::class, "generateMultipartBoundary", "-aabbcc-the-test-multipart-boundary-xxyyzz-");
        self::assertEquals("-aabbcc-the-test-multipart-boundary-xxyyzz-", $this->instance->multipartBoundary());
    }

    /** Ensure we don't call generateMultipartBoundary when the part already has one. */
    public function testMultipartBoundary2(): void
    {
        $this->mockMethod(Mime::class, "generateMultipartBoundary", fn (): mixed => self::fail("generateMultipartBoundary() should not be called."));
        $instance = new XRay($this->instance);
        $instance->multipartBoundary = "-112233-the-test-multipart-boundary-998877-";
        self::assertEquals("-112233-the-test-multipart-boundary-998877-", $this->instance->multipartBoundary());
    }

    /** Ensure we can immutably add a Part instance */
    public function testWithPart1(): void
    {
        $part = new Part("The extra part content.", "application/x-bead-framework-extra", "x-bead-encoding-extra");
        $instance = $this->instance->withPart($part);
        self::assertNotSame($this->instance, $part);

        $map = fn (Part $part): array => [
            $part->body(),
            $part->contentType(),
            $part->contentEncoding(),
        ];

        self::assertEqualsCanonicalizing(self::TestParts, array_map($map, $this->instance->parts()));
        self::assertEqualsCanonicalizing([...self::TestParts, $map($part),], array_map($map, $instance->parts()));
    }

    /** Ensure we can immutably add a Part from content, type and encoding */
    public function testWithPart2(): void
    {
        $part = ["The extra part content.", "application/x-bead-framework-extra", "x-bead-framework-encoding-extra",];
        $instance = $this->instance->withPart(...$part);
        self::assertNotSame($this->instance, $part);

        $map = fn (Part $part): array => [
            $part->body(),
            $part->contentType(),
            $part->contentEncoding(),
        ];

        self::assertEqualsCanonicalizing(self::TestParts, array_map($map, $this->instance->parts()));
        self::assertEqualsCanonicalizing([...self::TestParts, $part,], array_map($map, $instance->parts()));
    }

    /** Ensure we can add a part from just content */
    public function testWithPart3(): void
    {
        $part = ["The extra part content.", Part::DefaultContentType, Part::DefaultContentEncoding,];
        $instance = $this->instance->withPart($part[0]);
        self::assertNotSame($this->instance, $part);

        $map = fn (Part $part): array => [
            $part->body(),
            $part->contentType(),
            $part->contentEncoding(),
        ];

        self::assertEqualsCanonicalizing(self::TestParts, array_map($map, $this->instance->parts()));
        self::assertEqualsCanonicalizing([...self::TestParts, $part,], array_map($map, $instance->parts()));
    }

    /** Ensure we can add a part from just content and type */
    public function testWithPart4(): void
    {
        $part = ["The extra part content.", "application/x-bead-framework", Part::DefaultContentEncoding,];
        $instance = $this->instance->withPart($part[0], $part[1]);
        self::assertNotSame($this->instance, $part);

        $map = fn (Part $part): array => [
            $part->body(),
            $part->contentType(),
            $part->contentEncoding(),
        ];

        self::assertEqualsCanonicalizing(self::TestParts, array_map($map, $this->instance->parts()));
        self::assertEqualsCanonicalizing([...self::TestParts, $part,], array_map($map, $instance->parts()));
    }

    /** Ensure we can add a part from just content and encoding */
    public function testWithPart5(): void
    {
        $part = ["The extra part content.", Part::DefaultContentType, "x-bead-framework-encoding",];
        $instance = $this->instance->withPart($part[0], null, $part[2]);
        self::assertNotSame($this->instance, $part);

        $map = fn (Part $part): array => [
            $part->body(),
            $part->contentType(),
            $part->contentEncoding(),
        ];

        self::assertEqualsCanonicalizing(self::TestParts, array_map($map, $this->instance->parts()));
        self::assertEqualsCanonicalizing([...self::TestParts, $part,], array_map($map, $instance->parts()));
    }
}
