<?php

declare(strict_types=1);

namespace BeadTests\Web\Responses;

use Bead\Contracts\Web\Header as HeaderContract;
use Bead\Web\Header;
use Bead\Web\Responses\DownloadResponse;
use BeadTests\Framework\TestCase;

/** @covers \Bead\Web\Responses\DownloadResponse */
class DownloadResponseTest extends TestCase
{
    /** Ensure the constructor defaults to a 200 status code. */
    public function testConstructor1(): void
    {
        $actual = new DownloadResponse("");
        self::assertSame(200, $actual->statusCode());
    }

    /** Ensure the constructor defaults to the content type "application/octet-stream". */
    public function testConstructor2(): void
    {
        $actual = new DownloadResponse("");
        self::assertSame("application/octet-stream", $actual->contentType());
    }

    /** Ensure the download's file name is the empty string by default. */
    public function testConstructor3(): void
    {
        $actual = new DownloadResponse("");
        self::assertSame("", $actual->fileName());
    }

    /** Ensure the constructor uses the provided data for the response body. */
    public function testConstructor4(): void
    {
        $actual = new DownloadResponse("bead-framework");
        self::assertSame("bead-framework", $actual->content());
    }

    /** Ensure the content type can be set through the constructor. */
    public function testConstructor5(): void
    {
        $actual = new DownloadResponse("", "text/plain");
        self::assertSame("text/plain", $actual->contentType());
    }

    /** Ensure the filename can be set. */
    public function testFilename1(): void
    {
        $actual = new DownloadResponse("");
        $actual->setFileName("downloaded-file.txt");
        self::assertSame("downloaded-file.txt", $actual->fileName());
    }

    /** Ensure the filename can be set fluently. */
    public function testFilename2(): void
    {
        $response = new DownloadResponse("");
        $actual = $response->named("downloaded-file.txt");
        self::assertSame("downloaded-file.txt", $actual->fileName());
        self::assertInstanceOf(DownloadResponse::class, $actual);
    }

    /** Ensure the content type can be set fluently. */
    public function testOfType1(): void
    {
        $response = new DownloadResponse("");
        $actual = $response->ofType("text/plain");
        self::assertSame("text/plain", $actual->contentType());
        self::assertInstanceOf(DownloadResponse::class, $actual);
    }

    /** Ensure a download response starts life with a content-disposition header with the default filename. */
    public function testHeaders1(): void
    {
        $actual = (new DownloadResponse(""))->headers();
        self::assertCount(1, $actual);
        self::assertContainsOnlyInstancesOf(HeaderContract::class, $actual);
        usort($actual, static fn (HeaderContract $a, HeaderContract $b): int => $a->name() <=> $b->name());
        self::assertSame("content-disposition", strtolower($actual[0]->name()));
        self::assertSame("attachment; filename=\"download\"", strtolower($actual[0]->value()));
    }

    /** Ensure the response headers can be set. */
    public function testSetHeaders1(): void
    {
        $response = new DownloadResponse("");
        self::assertCount(1, $response->headers());

        $response->setHeaders([
            new Header("content-length", "42"),
            new Header("x-framework", "bead"),
        ]);

        $actual = $response->headers();
        self::assertCount(3, $actual);
        usort($actual, static fn (HeaderContract $a, HeaderContract $b): int => $a->name() <=> $b->name());
        self::assertSame("content-disposition", strtolower($actual[0]->name()));
        self::assertSame("attachment; filename=\"download\"", strtolower($actual[0]->value()));
        self::assertSame("content-length", strtolower($actual[1]->name()));
        self::assertSame("42", strtolower($actual[1]->value()));
        self::assertSame("x-framework", strtolower($actual[2]->name()));
        self::assertSame("bead", strtolower($actual[2]->value()));
    }

    /** Ensure the response headers can be set fluently. */
    public function testWithHeaders1(): void
    {
        $response = new DownloadResponse("");
        self::assertCount(1, $response->headers());

        $response = $response->withHeaders([
            new Header("content-length", "42"),
            new Header("x-framework", "bead"),
        ]);

        self::assertInstanceOf(DownloadResponse::class, $response);
        $actual = $response->headers();
        self::assertCount(3, $actual);
        usort($actual, static fn (HeaderContract $a, HeaderContract $b): int => $a->name() <=> $b->name());
        self::assertSame("content-disposition", strtolower($actual[0]->name()));
        self::assertSame("attachment; filename=\"download\"", strtolower($actual[0]->value()));
        self::assertSame("content-length", strtolower($actual[1]->name()));
        self::assertSame("42", strtolower($actual[1]->value()));
        self::assertSame("x-framework", strtolower($actual[2]->name()));
        self::assertSame("bead", strtolower($actual[2]->value()));
    }
}
