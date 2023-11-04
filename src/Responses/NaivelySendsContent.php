<?php

namespace Bead\Responses;

use Bead\Exceptions\HttpException;

/**
 * Trait for responses that simply send the status, headers and content without any further transformation.
 */
trait NaivelySendsContent
{
    use SendsHeaders;

    /**
     * Constrain the trait to classes that implement this method.
     * @return int The HTTP status code.
     */
    abstract public function statusCode(): int;

    /**
     * Constrain the trait to classes that implement this method.
     * @return string The HTTP content-type.
     */
    abstract public function contentType(): string;

    /**
     * Constrain the trait to classes that implement this method.
     * @return array<string,string> The HTTP headers.
     */
    abstract public function headers(): array;

    /**
     * Constrain the trait to classes that implement this method.
     * @return string The HTTP response body.
     */
    abstract public function content(): string;

    /**
     * Send the response.
     *
     * @throws HttpException
     */
    public function send(): void
    {
        http_response_code($this->statusCode());
        header("content-type: {$this->contentType()}", true);

        foreach ($this->headers() as $header => $value) {
            header("{$header}: {$value}", true);
        }

        echo $this->content();
    }
}
