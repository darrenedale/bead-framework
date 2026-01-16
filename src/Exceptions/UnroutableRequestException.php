<?php

namespace Bead\Exceptions;

use Bead\Contracts\Web\Request as RequestContract;
use Exception;
use Throwable;

/**
 * Exception thrown when a Router instance is unable to route a request.
 */
class UnroutableRequestException extends Exception
{
    private RequestContract $m_request;

    /**
     * Initialise a new UnroutableRequestException.
     *
     * @param RequestContract $request The request that could not be routed.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The previous throwable, if any. Defaults to null.
     */
    public function __construct(RequestContract $request, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_request = $request;
    }

    /**
     * The request that could not be routed.
     *
     * @return RequestContract The request.
     */
    public function getRequest(): RequestContract
    {
        return $this->m_request;
    }
}
