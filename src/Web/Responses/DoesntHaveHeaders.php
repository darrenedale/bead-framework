<?php

namespace Bead\Web\Responses;

use Bead\Contracts\Web\Header;

/**
 * Trait for responses that don't have any headers (other than content-type.
 *
 * Use this to avoid boilerplate.
 */
trait DoesntHaveHeaders
{
    /**
     * The (empty) array of headers.
     * @return Header[] The headers.
     */
    public function headers(): array
    {
        return [];
    }
}
