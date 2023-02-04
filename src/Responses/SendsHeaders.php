<?php

namespace Bead\Responses;

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
     */
	public abstract function headers(): array;

    /**
     * Constrain the trait to classes that implement the contentType() method.
     */
	public abstract function contentType(): string;

    /**
     * Send the headers.
     */
	protected function sendHeaders(): void
	{
		foreach ($this->headers() as $header => $value) {
			header("{$header}: {$value}", true);
		}

		header("content-type: {$this->contentType()}", true);
	}
}
