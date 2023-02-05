<?php

namespace Bead\Contracts\Database;

interface Connection
{
    /**
     * Prepare a statement for execution.
     *
     * @param string $sql The SQL for the prepared statement.
     *
     * @return Statement The prepared statement.
     */
    public function prepare(string $sql): Statement;

    /** Start a transaction. */
    public function beginTransaction(): bool;

    /** Check whether a transaction is in progress. */
    public function inTransaction(): bool;

    /** Commit the current transaction. */
    public function commit(): bool;

    /** Roll back the current transaction. */
    public function rollBack(): bool;

    /**
     * Create a new query builder.
     *
     * The query builder will be set up to use the connection when executed.
     *
     * @return QueryBuilder The created query builder.
     */
    public function createQuery(): QueryBuilder;

    /**
     * Fetch the ID of the last row inserted.
     *
     * @return int|string|null The ID, or `null` if there has not been an insert operation with a suitable primary key.
     */
    public function lastInsertId(): int|string|null;
}
