<?php

namespace Bead\Database;

use Bead\Application;
use Bead\Contracts\QueryBuilder as QueryBuilderContract;
use PDO;

class QueryBuilder implements QueryBuilderContract
{
    use BuildsQueries;
    use ExecutesQueries;

    /** @var PDO|null The connection to use to execute the query. */
    private ?PDO $connection;

    /**
     * Initialise a new QueryBuilder instance.
     */
    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Application::instance()->database();
    }

    /**
     * Fetch the connection to use when preparing and/or executing the query.
     * @return PDO The connection.
     */
    public function connection(): PDO
    {
        return $this->connection;
    }

    /**
     * Set the database connection to use when preparing and/or executing the query.
     *
     * @param PDO $connection The connection to use.
     */
    public function setConnection(PDO $connection): void
    {
        $this->connection = $connection;
    }
}
