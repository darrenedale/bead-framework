<?php

namespace Bead\Exceptions;

/**
 * Exception-response to use when a an internal error has occurred.
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
