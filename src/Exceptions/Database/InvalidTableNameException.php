<?php

namespace Bead\Exceptions\Database;

use Throwable;

/**
 * Exception thrown when a QueryBuilder encounters an invalid table (or table alias name).
 */
class InvalidTableNameException extends QueryBuilderException
{
    private string $m_name;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $name The table name (or alias) that is invalid in the QueryBuilder.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defualst to null.
     */
    public function __construct(string $name, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_name = $name;
    }

    /**
     * Fetch the table name (or alias) that is duplicated in the QueryBuilder.
     *
     * @return string The column name.
     */
    public function getTableName(): string
    {
        return $this->m_name;
    }

}