<?php

namespace Bead\Exceptions\Http;

use Bead\Web\Request;
use Throwable;

/** Exception thrown when a request fails CSRF verification. */
class CsrfTokenVerificationException extends HttpException
{
    /**
     * The HTTP status code.
     *
     * @return int 400 (Bad request).
     */
    public function statusCode(): int
    {
        return 400;
    }
}
