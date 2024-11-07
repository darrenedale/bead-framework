<?php

namespace BeadTests\Web\Responses;

use Bead\Web\Responses\RedirectResponse;
use BeadTests\Framework\TestCase;

class RedirectResponseTest extends TestCase
{
    /** @var string The test redirect URL. */
    private const string TestUrl = "/redirect";

    /** @var RedirectResponse The test fixture. */
    private RedirectResponse $response;

    public function setUp(): void
    {
        $this->response = new RedirectResponse(self::TestUrl);
    }

    public function tearDown(): void
    {
        unset($this->response);
        parent::tearDown();
    }

    /** Ensure the constructor uses the defalt HTTP response code. */
    public function testConstructor1(): void
    {
        self::assertEquals(RedirectResponse::DefaultRedirectCode, $this->response->statusCode());
    }

    /** Ensure the constructor uses the provided URL and HTTP status code. */
    public function testConstructor2(): void
    {
        $response = new RedirectResponse("/go/somewhere/else", RedirectResponse::PermanentRedirect);
        self::assertEquals("/go/somewhere/else", $response->url());
        self::assertEquals(RedirectResponse::PermanentRedirect, $response->statusCode());
    }

    /** Ensure we get the expected redirect location header. */
    public function testHeaders1(): void
    {
        self::assertEquals(["location" => "/redirect"], $this->response->headers());
    }

    /** Ensure we can set the redirect URL. */
    public function testSetUrl(): void
    {
        self::assertNotEquals("/go/somewhere/else", $this->response->url());
        $this->response->setUrl("/go/somewhere/else");
        self::assertEquals("/go/somewhere/else", $this->response->url());
    }

    /** Ensure the HTTP response code and headers are set. */
    public function testSend1(): void
    {
        $response = $this->response;

        $expectedHeaders = [
            "location: /redirect",
            "content-type: ",
        ];

        $httpResponseCode = function(int $statusCode) use ($response): void {
            TestCase::assertEquals($response->statusCode(), $statusCode);
        };

        $header = function(string $header, bool $replace) use (&$expectedHeaders): void {
            $expected = array_shift($expectedHeaders);
            TestCase::assertEquals($expected, $header);
            TestCase::assertTrue($replace);
        };

        $this->mockFunction("http_response_code", $httpResponseCode);
        $this->mockFunction("header", $header);
        $this->response->send();
        self::assertEmpty($expectedHeaders);
    }
}
