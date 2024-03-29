<?php

namespace Bead\Database;

use Bead\Exceptions\Database\OrphanedJoinException;
use LogicException;
use PDO;
use PDOException;
use PDOStatement;

trait ExecutesQueries
{
    /**
     * Fetch the SQL for the query to execute.
     * @return string The SQL.
     */
    abstract public function sql(): string;

    /**
     * Fetch the connection to use when preparing and/or executing the query.
     * @return PDO The connection.
     */
    abstract public function connection(): PDO;

    /**
     * Fetch a prepared statement for the query builder.
     *
     * @return PDOStatement The prepared statement.
     * @throws PDOException if the built query is not valid for the connection.
     * @throws LogicException if no connection is set.
     * @throws OrphanedJoinException if any of the configured joins references a non-existent table or alias.
     */
    public function prepare(): PDOStatement
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
     * @return PDOStatement The executed query.
     * @throws PDOException if the built query is not valid for the connection.
     * @throws LogicException if no connection is set.
     * @throws OrphanedJoinException if any of the configured joins references a non-existent table or alias.
     */
    public function execute(): PDOStatement
    {
        $stmt = $this->prepare();
        $stmt->execute();
        return $stmt;
    }
}
