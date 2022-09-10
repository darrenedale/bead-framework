<?php

namespace Equit\Database;

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
    public abstract function connection(): PDO;

    /**
     * Fetch a prepared statement for the query builder.
     *
     * @return PDOStatement The prepared statement.
     * @throws PDOException if the built query is not valid for the connection.
     */
    public function prepare(): PDOStatement
    {
        return $this->connection()->prepare($this->sql());
    }

    /**
     * Execute the query.
     *
     * @return PDOStatement The executed query.
     * @throws PDOException if the built query is not valid for the connection.
     */
    public function execute(): PDOStatement
    {
        $stmt = $this->prepare();
        $stmt->execute();
        return $stmt;
    }
}
