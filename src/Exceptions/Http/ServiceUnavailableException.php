<?php

namespace Bead\Exceptions\Http;

/**
 * Exception-response to use when a request can't be fulfilled because the service requested is unavailable.
 *
 * The main use case is when the application is in maintenance mode.
 */
class ServiceUnavailableException extends HttpException
{
    /**
     * The HTTP status code.
     *
     * @return int 404
     */
    public function statusCode(): int
    {
        return 503;
    }
}
