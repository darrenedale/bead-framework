<?php

namespace Bead\Exceptions\Database;

use Throwable;

/**
 * Exception thrown when a query builder encounters an invalid column.
 */
class InvalidColumnNameException extends QueryBuilderException
{
    private string $m_name;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $column The invalid column.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(string $column, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_name = $column;
    }

    /**
     * Fetch the invalid column.
     *
     * @return string The column.
     */
    public function getColumnName(): string
    {
        return $this->m_name;
    }
}