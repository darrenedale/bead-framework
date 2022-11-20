<?php

namespace Bead\Exceptions\Testing;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a StaticXRay object is constructed with a non-existent class.
 */
class StaticXRayException extends RuntimeException
{
    /** @var string The invalid x-rayed class. */
    private string $m_class;

    /**
     * Initialise a new exception instance.
     *
     * @param string $class The invalid class name.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to `0`.
     * @param Throwable|null $previous The options previous exception that was thrown. Defaults to `null`.
     */
    public function __construct(string $class, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_class = $class;
    }

    /**
     * Fetch the invalid class name.
     *
     * @return string The class name.
     */
    public function getClassName(): string
    {
        return $this->m_class;
    }
}
