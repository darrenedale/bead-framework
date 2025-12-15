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
            ["framework" => "bead"],
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
}
