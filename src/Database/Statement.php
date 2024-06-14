<?php

declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\Statement as StatementContract;
use Bead\Exceptions\Database\StatementException;
use Iterator;
use IteratorAggregate;
use PDO;
use PDOException;
use PDOStatement;
use Traversable;

/**
 * A prepared statement for execution by a database connection using PDO.
 * TODO custom exception classes?
 */
class Statement implements StatementContract, IteratorAggregate
{
    /** @var PDOStatement The underlying PDO Statement. */
    private PDOStatement $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
        $this->statement->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);
    }

    /** Helper to bind values to statmenet parameters. */
    private function bindValue(string|int $parameter, mixed $value): void
    {
        $previous = null;
        
        try {
            $successful = $this->statement->bindValue($parameter, $value, match (gettype($value)) {
                "integer" => PDO::PARAM_INT,
                "boolean" => PDO::PARAM_BOOL,
                "NULL" => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        } catch (PDOException $err) {
            $previous = $err;
            $successful = false;
        }

        if (!$successful) {
            throw new StatementException("Failed to bind value to parameter {$parameter}.", previous: $previous);
        }
    }

    /**
     * Bind a value to a named parameter.
     *
     * @param string $parameter The parameter to bind to.
     * @param mixed $value The value to bind.
     */
    public function bindNamedParameter(string $parameter, mixed $value): void
    {
        $this->bindValue($parameter, $value);
    }

    /**
     * Bind a value to a positional parameter.
     *
     * @param int $position The parameter position to bind to.
     * @param mixed $value The value to bind.
     */
    public function bindPositionalParameter(int $position, mixed $value): void
    {
        $this->bindValue($position, $value);
    }

    /**
     * Bind several named parameters at once.
     *
     * @param array<string,mixed> $values The values to bind to the parameters, keyed by parameter name.
     */
    public function bindNamedParameters(array $values): void
    {
        foreach ($values as $parameter => $value) {
            assert(is_string($parameter), new LogicException("Keys must be strings when binding named parameters."));
            $this->bindNamedParameter($parameter, $value);
        }
    }

    /**
     * Bind all parameters in one call.
     *
     * Parameters will be bound starting at the first parameter for the first value in the array.
     *
     * @param array<int,mixed> $values The values to bind to the parameters.
     */
    public function bindPositionalParameters(array $values, int $from = 0): void
    {
        foreach ($values as $value) {
            $this->bindPositionalParameter($from, $value);
            ++$from;
        }
    }

    /**
     * Execute the statement.
     *
     * @param array|null $arguments The values for the statement's parameters.
     *
     * @return bool `true` if the statement was executed, `false` if not.
     */
    public function execute(?array $arguments = null): bool
    {
        return $this->statement->execute($arguments);
    }

    /**
     * Fetch all the rows from the last execution of the statement.
     *
     * @return array<int,array<string,mixed>> The rows.
     */
    public function fetchAll(): array
    {
        try {
            return $this->statement->fetchAll();
        } catch (PDOException $err) {
            throw new StatementException("Failed to fetch the data.", previous: $err);
        }
    }

    /**
     * Fetch the next row from the last execution of the statement.
     *
     * @return array<string,mixed>|null The data for the next row.
     */
    public function fetchNext(): ?array
    {
        try {
            $row = $this->statement->fetch();
        } catch (PDOException $err) {
            throw new StatementException("Failed to fetch the next row of data.", previous: $err);
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Count the number of rows in the result from the last execution of the statement.
     *
     * @return int The number of rows.
     */
    public function count(): int
    {
        try {
            return $this->statement->rowCount();
        } catch (PDOException $err) {
            throw new StatementException("Failed to fetch row count.", previous: $err);
        }
    }

    /**
     * Fetch the iterator that implements the Traversable interface.
     *
     * @return Iterator The iterator.
     */
    public function getIterator(): Iterator
    {
        return $this->statement->getIterator();
    }
}
