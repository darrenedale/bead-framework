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
use Equit\XRay\StaticXRay;
use JsonException;
use ValueError;

/** @covers \Bead\Web\Request */
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
            [
                UploadedFile::create("the-file", "text/plain", __DIR__ . "/files/uploaded-file-1.txt", filesize(__DIR__ . "/files/uploaded-file-1.txt")),
                UploadedFile::create("the-file", "text/plain", __DIR__ . "/files/uploaded-file-1.txt", filesize(__DIR__ . "/files/uploaded-file-1.txt")),
                UploadedFile::create("another-file", "text/plain", __DIR__ . "/files/uploaded-file-2.txt", filesize(__DIR__ . "/files/uploaded-file-2.txt")),
            ],
            "{\"framework\": \"bead\"}",
        );
    }

    public function tearDown(): void
    {
        $xray = new StaticXRay(Request::class);
        $xray->capturedRequest = null;
        parent::tearDown();
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

    /** Ensure the port is reported as null when not set. */
    public function testPort1(): void
    {
        self::assertNull($this->m_request->port());
    }

    /** Ensure the port is reported correctly when set. */
    public function testPort2(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page"))->withPort(80)
        );
        self::assertSame(80, $request->port());
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

    /** Ensure has() reports presence of a query parameter correctly. */
    public function testHas1(): void
    {
        self::assertTrue($this->m_request->has("framework"));
    }

    /** Ensure has() reports presence of a form field correctly. */
    public function testHas2(): void
    {
        self::assertTrue($this->m_request->has("more-data"));
    }

    /** Ensure data() correctly returns query parameters and form fields. */
    public function testData1(): void
    {
        $actual = $this->m_request->data(["framework", "data"]);
        self::assertCount(2, $actual);
        self::assertSame("bead", $actual["framework"]);
        self::assertSame("value", $actual["data"]);
    }

    /** Ensure data() prioritises form fields over query parameters. */
    public function testData2(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            new Uri(\Bead\Contracts\Web\Uri::SchemeHttps, "example.org", "/home/page"),
            ["key1" => "value1", "key2" => "value2"],
            ["key2" => "value3"],
        );

        $actual = $request->data(["key1", "key2"]);
        self::assertCount(2, $actual);
        self::assertSame("value1", $actual["key1"]);
        self::assertSame("value3", $actual["key2"]);
    }

    /** Ensure data() can return a single value. */
    public function testData3(): void
    {
        self::assertSame("query-value", $this->m_request->data("query-key"));
    }

    /** Ensure data() skips missing keys and returns everything it can find. */
    public function testData4(): void
    {
        $actual = $this->m_request->data(["framework", "missing", "data"]);
        self::assertCount(2, $actual);
        self::assertSame("bead", $actual["framework"]);
        self::assertSame("value", $actual["data"]);
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

    /** Ensure cookie values are reported correctly. */
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

    /** Ensure the presence of an uploaded file is reported correctly. */
    public function testHasUploadedFile1(): void
    {
        self::assertTrue($this->m_request->hasUploadedFile("the-file"));
    }

    /** Ensure the absence of an uploaded file is reported correctly. */
    public function testHasUploadedFile2(): void
    {
        self::assertFalse($this->m_request->hasUploadedFile("missing-file"));
    }

    /** Ensure all uploaded files are reported correctly. */
    public function testUploadedFiles1(): void
    {
        $actual = $this->m_request->uploadedFiles();
        self::assertCount(3, $actual);
        usort($actual, static fn (UploadedFile $a, UploadedFile $b): int => $a->name() <=> $b->name());
        self::assertSame("another-file", $actual[0]->name());
        self::assertSame("the-file", $actual[1]->name());
        self::assertSame("the-file", $actual[2]->name());
    }

    /** Ensure named uploaded files are reported correctly.  */
    public function testUploadedFile1(): void
    {
        $actual = $this->m_request->uploadedFile("the-file");
        self::assertCount(2, $actual);
        self::assertSame("the-file", $actual[0]->name());
        self::assertSame("the-file", $actual[1]->name());
    }

    /** Ensure an empty array is returned for uploaded files that don't exist. */
    public function testUploadedFile2(): void
    {
        self::assertSame([], $this->m_request->uploadedFile("missing-file"));
    }

    /** Ensure the request body is reported correctly. */
    public function testBody1(): void
    {
        self::assertSame("{\"framework\": \"bead\"}", $this->m_request->body());
    }

    /** Ensure the captured request body is read from standard input. */
    public function testBody2(): void
    {
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTP_HOST"] = "example.org";
        $_SERVER["REQUEST_URI"] = "/";
        $_SERVER["QUERY_STRING"] = "";

        $this->mockFunction("file_get_contents", static function (string $path): false|string {
            RequestTest::assertSame("php://input", $path);
            return "read from mock standard input";
        });

        $request = Request::capture();
        self::assertSame("read from mock standard input", $request->body());
    }

    /** Ensure failure to read the request body from standard input throws the expected exception. */
    public function testBody3(): void
    {
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTP_HOST"] = "example.org";
        $_SERVER["REQUEST_URI"] = "/";
        $_SERVER["QUERY_STRING"] = "";

        $this->mockFunction("file_get_contents", static function (string $path): false|string {
            RequestTest::assertSame("php://input", $path);
            return false;
        });

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Unable to read request body");
        Request::capture()->body();
    }

    /** Ensure whether the request's body is JSON is reported correctly. */
    public function testIsJson1(): void
    {
        self::assertTrue($this->m_request->isJson());
    }

    /** Ensure whether the request's body is JSON is reported correctly. */
    public function testIsJson2(): void
    {
        self::assertFalse(Request::create(
            HttpMethod::Get,
            new Uri("https", "example.org", "/home/page"),
            headers: [new Header("content-type", "text/plain")]
        )->isJson());
    }

    /** Ensure requests with no content-type are not reported as JSON. */
    public function testIsJson3(): void
    {
        self::assertFalse(Request::create(
            HttpMethod::Get,
            new Uri("https", "example.org", "/home/page"),
        )->isJson());
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

    /** Ensure a RequestException is thrown when fetching the JSON of a request without a content type. */
    public function testJson3(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page")),
        );

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Request body is not JSON");
        $request->json();
    }

    /** Ensure the body is transcoded to UTF-8 if necessary before being JSON-decoded. */
    public function testJson4(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page")),
            headers: [new Header("content-type","application/json; charset=utf-16le"),],
            body: "\x7B\x00\x22\x00\x66\x00\x72\x00\x61\x00\x6D\x00\x65\x00\x77\x00\x6F\x00\x72\x00\x6B\x00\x22\x00\x3A\x00\x22\x00\x62\x00\x65\x00\x61\x00\x64\x00\x22\x00\x7D\x00",
        );

        self::assertSame(["framework" => "bead",], $request->json());
    }

    /** Ensure the expected exception is thrown if transcoding fails. */
    public function testJson5(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page")),
            headers: [new Header("content-type","application/json; charset=utf-16le"),],
            body: "{\"framework\":\"bead\"}",
        );

        $this->mockFunction("mb_convert_encoding", static fn() => throw new ValueError("Test transcoding exception"));
        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Unable to convert request body to UTF-8: Test transcoding exception");
        $request->json();
    }

    /** Ensure a JsonException is thrown if the content can't be decoded as JSON. */
    public function testJson6(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page")),
            headers: [new Header("content-type","application/json"),],
            body: "{\"framework\":\"bead\"",
        );

        $this->expectException(JsonException::class);
        $request->json();
    }

    /** Ensure AJAX requests are correctly reported. */
    public function testIsAjax1(): void
    {
        $request = Request::create(
            HttpMethod::Get,
            (new Uri(UriContract::SchemeHttps, "example.org", "/home/page")),
            headers: [new Header("X-Requested-With","XMLHttpRequest"),],
        );
        self::assertTrue($request->isAjax());
    }

    /** Ensure non-AJAX requests are correctly reported. */
    public function testIsAjax2(): void
    {
        self::assertFalse($this->m_request->isAjax());
    }

    /** Ensure the captured request is as expected. */
    public function testCapture1(): void
    {
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTPS"] = 1;
        $_SERVER["HTTP_HOST"] = "example.org:8080";
        $_SERVER["REQUEST_URI"] = "/home/page";
        $_SERVER["QUERY_STRING"] = "framework=bead";
        $_SERVER["HTTP_CONTENT_TYPE"] = "application/json";
        $_SERVER["CONTENT_LENGTH"] = "14";
        $_SERVER["CONTENT_TYPE"] = "text/plain";
        $_GET["framework"] = "bead";
        $_POST["data"] = "value";
        $_COOKIE["bead-session"] = "BvAd6yebhDZgcPODKn1Cll7KQ6m4fxjYmfZzSUgM-5MJsvQEEUnpW7ykEBzt5HrR";

        $_FILES["file"] = [
            "name" => "file",
            "type" => "application/octet-stream",
            "tmp_name" => "/tmp/file",
            "error" => UPLOAD_ERR_OK,
            "size" => 14,
        ];

        $request = Request::capture();
        self::assertSame(HttpMethod::Get, $request->method());
        self::assertSame("https", $request->scheme());
        self::assertSame("example.org", $request->host());
        self::assertSame(8080, $request->port());
        self::assertSame("framework=bead", $request->query());

        $actual = $request->allQueryParameters();
        self::assertCount(1, $actual);
        self::assertSame("bead", $actual["framework"]);

        $actual = $request->allFormFields();
        self::assertCount(1, $actual);
        self::assertSame("value", $actual["data"]);

        $actual = $request->cookies();
        self::assertCount(1, $actual);
        self::assertSame("BvAd6yebhDZgcPODKn1Cll7KQ6m4fxjYmfZzSUgM-5MJsvQEEUnpW7ykEBzt5HrR", $actual["bead-session"]);

        $actual = $request->header("content-type");
        self::assertCount(1, $actual);
        self::assertSame("application/json", $actual[0]->value());

        $actual = $request->header("content-length");
        self::assertCount(1, $actual);
        self::assertSame("14", $actual[0]->value());

        self::assertCount(1, $request->uploadedFiles());
        $actual = $request->uploadedFile("file");
        self::assertCount(1, $actual);
        self::assertSame("file", $actual[0]->name());
        self::assertSame("application/octet-stream", $actual[0]->mediaType());
        self::assertSame(14, $actual[0]->reportedSize());
        self::assertSame("/tmp/file", $actual[0]->path());
        self::assertSame(UPLOAD_ERR_OK, $actual[0]->error());
    }

    /** Ensure a host with no port is captured correctly. */
    public function testCapture2(): void
    {
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTP_HOST"] = "example.org";
        $_SERVER["REQUEST_URI"] = "/";
        $_SERVER["QUERY_STRING"] = "";
        self::assertNull(Request::capture()->port());
    }

    /** Ensure the captured request captures array files. */
    public function testCapture3(): void
    {
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["HTTP_HOST"] = "example.org";
        $_SERVER["REQUEST_URI"] = "/";
        $_SERVER["QUERY_STRING"] = "";
        $_FILES["file"] = [
            "name" => [
                "file",
                "file",
            ],
            "type" => [
                "application/octet-stream",
                "application/json",
            ],
            "tmp_name" => [
                "/tmp/file",
                "/tmp/file2",
            ],
            "size" => [
                14,
                42,
            ],
            "error" => [
                UPLOAD_ERR_OK,
                UPLOAD_ERR_OK,
            ],
        ];

        $actual = Request::capture()->uploadedFile("file");
        self::assertCount(2, $actual);
        usort($actual, static fn (UploadedFile $a, UploadedFile $b): int => $a->reportedSize() <=> $b->reportedSize());
        self::assertSame("file", $actual[0]->name());
        self::assertSame("file", $actual[1]->name());
        self::assertSame("application/octet-stream", $actual[0]->mediaType());
        self::assertSame("application/json", $actual[1]->mediaType());
        self::assertSame("/tmp/file", $actual[0]->path());
        self::assertSame("/tmp/file2", $actual[1]->path());
        self::assertSame(14, $actual[0]->reportedSize());
        self::assertSame(42, $actual[1]->reportedSize());
        self::assertSame(UPLOAD_ERR_OK, $actual[0]->error());
        self::assertSame(UPLOAD_ERR_OK, $actual[1]->error());
    }
}
