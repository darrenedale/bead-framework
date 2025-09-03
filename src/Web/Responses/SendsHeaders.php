<?php

namespace Bead\Web\Responses;

use Bead\Contracts\Web\Header;

/**
 * Trait to endow a response with a method to send the HTTP headers.
 *
 * Use this when the headers can be sent without further validation to avoid boilerplate. Call sendHeaders() from your
 * send() implementation.
 */
trait SendsHeaders
{
    /**
     * Constrain the trait to classes that implement the headers() method.
     *
     * @return Header[]
     */
    abstract public function headers(): array;

    /** Constrain the trait to classes that implement the contentType() method. */
    abstract public function contentType(): string;

    /** Send the headers. */
    protected function sendHeaders(): void
    {
        foreach ($this->headers() as $header) {
            header($header->line(), false);
        }

        header("Content-Type: {$this->contentType()}", true);
    }
}
