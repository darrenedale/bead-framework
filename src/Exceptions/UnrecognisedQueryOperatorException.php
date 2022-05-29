<?php

namespace Equit\Exceptions;

use Throwable;

/**
 * Exception thrown when a model query is requested with an unrecognised operator.
 */
class UnrecognisedQueryOperatorException extends ModelException
{
    /** @var string The unrecognised operator. */
    private string $m_operator;

    /**
     * @param string $operator The unrecognised operator.
     * @param string $message The optional message, Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(string $operator, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_operator = $operator;
    }

    /**
     * Fetch the unrecognised operator.
     *
     * @return string The operator.
     */
    public function getOperator(): string
    {
        return $this->m_operator;
    }
}
