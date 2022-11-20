<?php

namespace Bead\Exceptions;

use Throwable;

/**
 * Exception thrown when an operator for a query builder is not valid.
 *
 * This usually means the operator is empty.
 */
class InvalidOperatorException extends QueryBuilderException
{
    /** @var string The invalid expression. */
    private string $m_operator;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $operator The invalid operator.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to `null`.
     */
    public function __construct(string $operator, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_operator = $operator;
    }

    /**
     * Fetch the invalid operator.
     *
     * @return string The operator.
     */
    public function getOperator(): string
    {
        return $this->m_operator;
    }
}