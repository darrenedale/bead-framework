<?php

namespace Bead\Exceptions;

use Throwable;

/**
 * Exception thrown when a raw expression for a query builder is not valid.
 *
 * This usually means the expression is empty.
 */
class InvalidQueryExpressionException extends QueryBuilderException
{
    /** @var string The invalid expression. */
    private string $m_expression;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $operator The invalid expression.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to `null`.
     */
    public function __construct(string $operator, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_expression = $operator;
    }

    /**
     * Fetch the invalid expression.
     *
     * @return string The expression.
     */
    public function getExpression(): string
    {
       return $this->m_expression;
    }
}