<?php

namespace Equit\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

class ServiceNotFoundException extends Exception implements NotFoundExceptionInterface
{
    private string $m_service;

    /**
     * Initialise a new instance of the exception.
     *
     * Reimplemented only to enforce strong types.
     *
     * @param string $message The exception message.
     * @param int $code The optional exception code. Defaults to 0.
     * @param Throwable|null $previous The optional previous ecxeption. Defualts to `null`.
     */
    public function __construct(string $service, string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_service = $service;
    }

    public function getService(): string
    {
        return $this->m_service;
    }
}
