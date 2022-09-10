<?php

namespace Equit\Exceptions;

use Throwable;

/**
 * Exception thrown when a query builder compiles a query containing joins that don't identify a table in the builder.
 */
class OrphanedJoinException extends QueryBuilderException
{
    /** @var string The table (or alias) that the join expects to be in the query. */
    private string $m_table;

    /**
     * @param string $table The table or alias that the join depends on in the query.
     * @param string $message The optional error message. Defaults to an empty string.
     * @param int $code The optional error code. Defaults to 0.
     * @param Throwable|null $previous The optional previous throwable. Defaults to null.
     */
    public function __construct(string $table, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_table = $table;
    }

    /**
     * Fetch the table missing from the query that the join depends on.
     * @return string The table or alias.
     */
    public function getTable(): string
    {
        return $this->m_table;
    }
}
