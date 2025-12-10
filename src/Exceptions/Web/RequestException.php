<?php

declare(strict_types=1);

namespace Bead\Exceptions\Web;

use Bead\Contracts\Web\Request as RequestContract;
use RuntimeException;
use Throwable;

/** Exception thrown when an operation on a Request produces an error condition. */
class RequestException extends RuntimeException
{
    /** @var RequestContract The request that caused the error. */
    private RequestContract $request;

    public function __construct(RequestContract $request, string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->request = $request;
    }

    public function getRequest(): RequestContract
    {
        return $this->request;
    }
}
