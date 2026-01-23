<?php

declare(strict_types=1);

namespace BeadTests\Web\Responses;

use Bead\Web\Responses\FileDownloadResponse;
use BeadTests\Framework\TestCase;

/** @covers \Bead\Web\Responses\FileDownloadResponse */
class FileDownloadResponseTest extends TestCase
{
    /** Ensure the constructor uses the default content type. */
    public function testConstructor1(): void
    {
        $actual = new FileDownloadResponse("");
        self::assertSame("application/octet-stream", $actual->contentType());
    }

    /** Ensure the source file can be set through the constructor. */
    public function testConstructor2(): void
    {
        $actual = new FileDownloadResponse("/tmp/file-download.txt");
        self::assertSame("/tmp/file-download.txt", $actual->sourceFile());
    }

    /** Ensure the content type can be set through the constructor. */
    public function testConstructor3(): void
    {
        $actual = new FileDownloadResponse("", "text/plain");
        self::assertSame("text/plain", $actual->contentType());
    }

    /** Ensure the source file can be set. */
    public function testSetSourceFile1(): void
    {
        $actual = new FileDownloadResponse("");
        $actual->setSourceFile("/tmp/file-download.txt");
        self::assertSame("/tmp/file-download.txt", $actual->sourceFile());
    }

    /** Ensure the source file can be set fluently. */
    public function testFromFile1(): void
    {
        $response = new FileDownloadResponse("");
        $actual = $response->fromFile("/tmp/file-download.txt");
        self::assertInstanceOf(FileDownloadResponse::class, $actual);
        self::assertSame("/tmp/file-download.txt", $actual->sourceFile());
    }

    /** Ensure the response is sent correctly. */
    public function testSend1(): void
    {
        $sentHeaders = [];
        $this->mockFunction("http_response_code", static fn (int $code) => TestCase::assertSame(200, $code));
        $this->mockFunction("readfile", static fn (string $filename) => TestCase::assertSame("/tmp/file-download.txt", $filename));

        $this->mockFunction("header", static function (string $headerLine, bool $replace) use (&$sentHeaders): void {
            $sentHeaders[] = $headerLine;
        });

        (new FileDownloadResponse("/tmp/file-download.txt"))->send();
        self::assertCount(2, $sentHeaders);
        sort($sentHeaders);
        self::assertSame("content-type: application/octet-stream", strtolower($sentHeaders[0]));
        self::assertSame("content-disposition: attachment; filename=\"download\"", strtolower($sentHeaders[1]));

        // proves that the two assertions in the mocked functions have been evaluated
        self::assertSame(5, $this->getCount());
    }
}
