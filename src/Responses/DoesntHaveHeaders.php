<?php

namespace Equit\Responses;

/**
 * Trait for responses that don't have any headers (other than content-type.
 *
 * Use this to avoid boilerplate.
 */
trait DoesntHaveHeaders
{
    /**
     * The (empty) array of headers.
     * @return array The headers.
     */
	public function headers(): array
	{
		return [];
	}
}
