<?php

namespace BeadTests\Web;

use Bead\Contracts\Web\Uri as UriContract;
use Bead\Web\Header;
use Bead\Web\HttpMethod;
use Bead\Web\Request;
use Bead\Web\UploadedFile;
use Bead\Web\Uri;
use BeadTests\Framework\TestCase;

class RequestTest extends TestCase
{
    private Request $m_request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m_request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page"))
                ->withQuery("framework=bead")
                ->withFragment("top"),
            ["framework" => "bead", "query-key" => "query-value",],
            ["data" => "value"],
            ["bead-session" => "BvAd6yebhDZgcPODKn1Cll7KQ6m4fxjYmfZzSUgM-5MJsvQEEUnpW7ykEBzt5HrR"],
            [new Header("content-type","application/json")],
            [UploadedFile::create("the-file", "text/plain", __DIR__ . "/files/uploaded-file.txt", filesize(__DIR__ . "/files/uploaded-file.txt"))],
            "{\"framework\": \"bead\"}",
        );
    }

    /** Ensure the method is reported correctly. */
    public function testMethod1(): void
    {
        self::assertSame(HttpMethod::Get, $this->m_request->method());
    }

    /** Ensure the scheme is reported correctly. */
    public function testScheme1(): void
    {
        self::assertSame(UriContract::SchemeHttps, $this->m_request->scheme());
    }

    /** Ensure the host is reported correctly. */
    public function testHost1(): void
    {
        self::assertSame("example.org", $this->m_request->host());
    }

    /** Ensure the path is reported correctly. */
    public function testPath1(): void
    {
        self::assertSame("/home/page", $this->m_request->path());
    }

    /** Ensure the query string is reported correctly. */
    public function testQuery1(): void
    {
        self::assertSame("framework=bead", $this->m_request->query());
    }

    /** Ensure the URI is reported correctly. */
    public function testUri1(): void
    {
        $actual = $this->m_request->uri();
        self::assertSame(UriContract::SchemeHttps, $actual->scheme());
        self::assertSame("example.org", $actual->host());
        self::assertSame("/home/page", $actual->path());
        self::assertSame("framework=bead", $actual->query());
        self::assertSame("top", $actual->fragment());
    }

    /** Ensure the presence of a query parameter is correctly reported. */
    public function testHasQueryParameter1(): void
    {
        self::assertTrue($this->m_request->hasQueryParameter("framework"));
    }

    /** Ensure the absence of a query parameter is correctly reported. */
    public function testHasQueryParameter2(): void
    {
        self::assertFalse($this->m_request->hasQueryParameter("bead"));
    }

    /** Ensure query parameter values are reported correctly. */
    public function testQueryParameter1(): void
    {
        self::assertSame("bead", $this->m_request->queryParameter("framework"));
    }

    /** Ensure null is returned for query parameters that don't exist. */
    public function testQueryParameter2(): void
    {
        self::assertNull($this->m_request->queryParameter("bead"));
    }

    /** Ensure a subset of query parameters can be fetched correctly. */
    public function testQueryParameters1(): void
    {
        self::assertSame(["query-key" => "query-value"], $this->m_request->queryParameters(["query-key"]));
    }

    /** Ensure all query parameters can be fetched. */
    public function testAllQueryParameters1(): void
    {
        $actual = $this->m_request->allQueryParameters();
        self::assertCount(2, $actual);
        self::assertSame("query-value", $actual["query-key"]);
        self::assertSame("bead", $actual["framework"]);
    }
}
