<?php

namespace Bead\Responses;

/**
 * Trait for responses that can have their content-type set.
 */
trait CanSetContentType
{
    /** @var string The content type. */
	private string $m_contentType = "application/octet-stream";

    /**
     * Set the response content-type header value.
     *
     * @param string $type The content-type.
     */
	public function setContentType(string $type): void
	{
		$this->m_contentType = $type;
	}

    /**
     * Fetch the response content-type header value.
     *
     * @return string The content-type.
     */
	public function contentType(): string
	{
		return $this->m_contentType;
	}
}
