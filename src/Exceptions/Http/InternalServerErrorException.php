<?php

namespace Bead\Exceptions\Http;

/**
 * Exception-response to use when an internal error has occurred.
 */
class InternalServerErrorException extends HttpException
{
    /**
     * The HTTP status code.
     * @return int 500
     */
    public function statusCode(): int
    {
        return 500;
    }
}
