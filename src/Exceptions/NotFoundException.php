<?php

namespace Bead\Exceptions;

/**
 * Exception-response to use when a request can't be fulfilled because it identifies a resource that does not exist.
 */
class NotFoundException extends HttpException
{
    /**
     * The HTTP status code.
     *
     * @return int 404
     */
    public function statusCode(): int
    {
        return 404;
    }
}
