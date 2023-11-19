<?php

namespace Bead\Database;

use Bead\Contracts\Database\Connection as DatabaseConnectionContract;
use Bead\Contracts\Database\Statement as DatabaseStatementContract;
use Bead\Exceptions\Database\OrphanedJoinException;
use LogicException;
use RuntimeException;

trait ExecutesQueries
{
    /**
     * Fetch the SQL for the query to execute.
     * @return string The SQL.
     */
    abstract public function sql(): string;

    /**
     * Fetch the connection to use when preparing and/or executing the query.
     * @return DatabaseConnectionContract The connection.
     */
    abstract public function connection(): DatabaseConnectionContract;

    /**
     * Fetch a prepared statement for the query builder.
     *
     * @return DatabaseStatementContract The prepared statement.
     * @throws RuntimeException if the built query is not valid for the connection.
     * @throws LogicException if no connection is set.
     * @throws OrphanedJoinException if any of the configured joins references a non-existent table or alias.
     */
    public function prepare(): DatabaseStatementContract
    {
        $connection = $this->connection();

        if (!isset($connection)) {
            throw new LogicException("No database connection set for executing query.");
        }

        return $connection->prepare($this->sql());
    }

    /**
     * Execute the query.
     *
     * @return DatabaseStatementContract The executed query.
     * @throws RuntimeException if the built query is not valid for the connection.
     * @throws LogicException if no connection is set.
     * @throws OrphanedJoinException if any of the configured joins references a non-existent table or alias.
     */
    public function execute(): DatabaseStatementContract
    {
        $stmt = $this->prepare();
        $stmt->execute();
        return $stmt;
    }
}
