<?php

namespace Bead\Exceptions;

use Throwable;

/**
 * Exception thrown when an ORDER BY clause in a QueryBuilder has been given an invalid direction.
 */
class InvalidOrderByDirection extends QueryBuilderException
{
    /** @var string The invalid direction. */
    private string $m_direction;

    /**
     * @param string $direction The invalid direction.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(string $direction, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_direction = $direction;
    }

    /**
     * Fetch the invalid direction.
     * @return string
     */
    public function getDirection(): string
    {
        return $this->m_direction;
    }
}
