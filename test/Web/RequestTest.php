<?php

namespace BeadTests\Web;

use Bead\Contracts\Web\Uri as UriContract;
use Bead\Exceptions\Web\RequestException;
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
            ["data" => "value", "more-data" => "another-value"],
            ["bead-session" => "BvAd6yebhDZgcPODKn1Cll7KQ6m4fxjYmfZzSUgM-5MJsvQEEUnpW7ykEBzt5HrR", "foo" => "bar"],
            [
                new Header("content-type","application/json"),
                new Header("x-multi-header","value 1"),
                new Header("X-Multi-Header","value 2"),
            ],
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

    /** Ensure the presence of a form field is correctly reported. */
    public function testHasFormField1(): void
    {
        self::assertTrue($this->m_request->hasFormField("data"));
    }

    /** Ensure the absence of a form field is correctly reported. */
    public function testHasFormField2(): void
    {
        self::assertFalse($this->m_request->hasFormField("value"));
    }

    /** Ensure form field values are reported correctly. */
    public function testFormField1(): void
    {
        self::assertSame("value", $this->m_request->formField("data"));
    }

    /** Ensure null is returned for form fields that don't exist. */
    public function testFormField2(): void
    {
        self::assertNull($this->m_request->formField("value"));
    }

    /** Ensure a subset of form fields can be fetched correctly. */
    public function testFormFields1(): void
    {
        self::assertSame(["data" => "value"], $this->m_request->formFields(["data"]));
    }

    /** Ensure all form fields can be fetched. */
    public function testAllFormFields1(): void
    {
        $actual = $this->m_request->allFormFields();
        self::assertCount(2, $actual);
        self::assertSame("value", $actual["data"]);
        self::assertSame("another-value", $actual["more-data"]);
    }

    /** Ensure the presence of a cookie is correctly reported. */
    public function testHasCookie1(): void
    {
        self::assertTrue($this->m_request->hasCookie("bead-session"));
    }

    /** Ensure the absence of a cookie is correctly reported. */
    public function testHasCookie2(): void
    {
        self::assertFalse($this->m_request->hasCookie("something-else"));
    }

    /** Ensure form cookie are reported correctly. */
    public function testCookie1(): void
    {
        self::assertSame("BvAd6yebhDZgcPODKn1Cll7KQ6m4fxjYmfZzSUgM-5MJsvQEEUnpW7ykEBzt5HrR", $this->m_request->cookie("bead-session"));
    }

    /** Ensure null is returned for cookies that don't exist. */
    public function testCookie2(): void
    {
        self::assertNull($this->m_request->cookie("missing-cookie"));
    }

    /** Ensure all cookies can be fetched. */
    public function testCookies1(): void
    {
        $actual = $this->m_request->cookies();
        self::assertCount(2, $actual);
        self::assertSame("bar", $actual["foo"]);
        self::assertSame("BvAd6yebhDZgcPODKn1Cll7KQ6m4fxjYmfZzSUgM-5MJsvQEEUnpW7ykEBzt5HrR", $actual["bead-session"]);
    }

    /** Ensure the presence of a header is reported correctly. */
    public function testHasHeader1(): void
    {
        self::assertTrue($this->m_request->hasHeader("content-type"));
    }

    /** Ensure the absence of a header is reported correctly. */
    public function testHasHeader2(): void
    {
        self::assertFalse($this->m_request->hasHeader("content-transfer-encoding"));
    }

    /** Ensure all the headers are reported correctly. */
    public function testHeaders1(): void
    {
        $actual = $this->m_request->headers();
        self::assertCount(3, $actual);
        usort($actual, static fn (Header $a, Header $b): int => strtolower($a->name()) <=> strtolower($b->name()) ?: strtolower($a->value()) <=> strtolower($b->value()));
        self::assertSame("content-type", $actual[0]->name());
        self::assertSame("application/json", $actual[0]->value());
        self::assertSame("x-multi-header", $actual[1]->name());
        self::assertSame("value 1", $actual[1]->value());
        self::assertSame("X-Multi-Header", $actual[2]->name());
        self::assertSame("value 2", $actual[2]->value());
    }

    /** Ensure all of a header's values are reported. */
    public function testHeader1(): void
    {
        $actual = $this->m_request->header("x-multi-header");
        self::assertCount(2, $actual);
        usort($actual, static fn (Header $a, Header $b): int => $a->value() <=> $b->value());
        self::assertSame("x-multi-header", $actual[0]->name());
        self::assertSame("value 1", $actual[0]->value());
        self::assertSame("X-Multi-Header", $actual[1]->name());
        self::assertSame("value 2", $actual[1]->value());
    }

    /** Ensure header name matching is not case sensitive. */
    public function testHeader2(): void
    {
        $actual = $this->m_request->header("X-MULTI-HEADER");
        self::assertCount(2, $actual);
        usort($actual, static fn (Header $a, Header $b): int => $a->value() <=> $b->value());
        self::assertSame("x-multi-header", $actual[0]->name());
        self::assertSame("value 1", $actual[0]->value());
        self::assertSame("X-Multi-Header", $actual[1]->name());
        self::assertSame("value 2", $actual[1]->value());
    }

    /** Ensure an empty array is returned for a named header that does not exist. */
    public function testHeader3(): void
    {
        self::assertSame([], $this->m_request->header("Content-Transfer-Encoding"));
    }

    public function testBody1(): void
    {
        self::assertSame("{\"framework\": \"bead\"}", $this->m_request->body());
    }

    /** Ensure decoded JSON is correctly returned when the content type is application/json. */
    public function testJson1(): void
    {
        self::assertSame(["framework" => "bead",], $this->m_request->json());
    }

    /** Ensure a RequestException is thrown when fetching the JSON of a request whose content type isn't application/json. */
    public function testJson2(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page")),
            headers: [new Header("content-type","text/plain"),],
        );

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Request body is not JSON");
        $request->json();
    }

    /** Ensure the body is transcoded to UTF-8 if necessary before being JSON-decoded. */
    public function testJson3(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page")),
            headers: [new Header("content-type","application/json; charset=utf-16le"),],
            body: "\x7B\x00\x22\x00\x66\x00\x72\x00\x61\x00\x6D\x00\x65\x00\x77\x00\x6F\x00\x72\x00\x6B\x00\x22\x00\x3A\x00\x22\x00\x62\x00\x65\x00\x61\x00\x64\x00\x22\x00\x7D\x00",
        );

        self::assertSame(["framework" => "bead",], $request->json());
    }
}
