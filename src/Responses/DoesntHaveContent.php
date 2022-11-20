<?php

namespace Bead\Responses;

/**
 * Trait for responses that have no content.
 */
trait DoesntHaveContent
{
    /**
     * The HTTP content type.
     *
     * @return string An empty string.
     */
	public function contentType(): string
	{
		return "";
	}

    /**
     * The (empty) content.
     *
     * @return string An empty string.
     */
	public function content(): string
	{
		return "";
	}
}
