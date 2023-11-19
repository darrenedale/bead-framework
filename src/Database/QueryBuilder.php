<?php

namespace Bead\Database;

use Bead\Contracts\Database\Connection as DatabaseConnectionContract;
use Bead\Contracts\Database\QueryBuilder as QueryBuilderContract;
use Bead\Core\Application;

class QueryBuilder implements QueryBuilderContract
{
    use BuildsQueries;
    use ExecutesQueries;

    /** @var DatabaseConnectionContract|null The connection to use to execute the query. */
    private ?DatabaseConnectionContract $connection;

    /**
     * Initialise a new QueryBuilder instance.
     */
    public function __construct(?DatabaseConnectionContract $connection = null)
    {
        $this->connection = $connection ?? Application::instance()->database();
    }

    /**
     * Fetch the connection to use when preparing and/or executing the query.
     * @return DatabaseConnectionContract The connection.
     */
    public function connection(): DatabaseConnectionContract
    {
        return $this->connection;
    }

    /**
     * Set the database connection to use when preparing and/or executing the query.
     *
     * @param DatabaseConnectionContract $connection The connection to use.
     */
    public function setConnection(DatabaseConnectionContract $connection): void
    {
        $this->connection = $connection;
    }
}
