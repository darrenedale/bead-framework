<?php

namespace Bead\Exceptions\Database;

use Throwable;

/**
 * Exception thrown when an attempt is made to add a column name to a QueryBuilder that already has that column name.
 *
 * This can be when two columns with the same name are added to the select clause, when the same alias is used for two
 * columns or when an alias clashes with a colum name without an alias.
 */
class DuplicateColumnNameException extends QueryBuilderException
{
    private string $m_name;

    /**
     * Initialise a new instance of the exception.
     *
     * @param string $name The column name (or alias) that is duplicated in the QueryBuilder.
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
     * Fetch the column name (or alias) that is duplicated in the QueryBuilder.
     *
     * @return string The column name.
     */
    public function getColumnName(): string
    {
        return $this->m_name;
    }
}
