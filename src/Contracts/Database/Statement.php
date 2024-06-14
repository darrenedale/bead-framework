<?php

declare(strict_types=1);

namespace Bead\Contracts\Database;

use Bead\Exceptions\Database\StatementException;
use \Traversable;

/** Interface for prepared statements created by database connections. */
interface Statement extends Traversable
{
    /**
     * Bind a value to a named parameter.
     *
     * @param string $parameter The parameter to bind to.
     * @param mixed $value The value to bind.
     */
    public function bindNamedParameter(string $paramenter, mixed $value): void;

    /**
     * Bind a value to a positional parameter.
     *
     * @param int $position The parameter position to bind to.
     * @param mixed $value The value to bind.
     */
    public function bindPositionalParameter(int $paramenter, mixed $value): void;

    /**
     * Bind several named parameters at once.
     *
     * @param array<string,mixed> $values The values to bind to the parameters, keyed by parameter name.
     */
    public function bindNamedParameters(array $values): void;

    /**
     * Bind all positional parameters.
     *
     * Parameters will be bound starting at the first parameter for the first value in the array.
     *
     * @param mixed[] $values The values to bind to the parameters.
     * @param int $from The positional parameter index for the first value.
     *
     * @return int The index of the first positional parameter that was not bound by a provided value. This enables you
     * to bind values in batches. Note that the index returned is not guaranteed to be a valid position if the last
     * available positional parameter had a value bound from the array provided (i.e. if you bind all the positional
     * parameters, you'll get back the index of the next parameter, which doesn't actually exist in the statement).
     */
    public function bindPositionalParameters(array $values, int $from = 0): int;

    /**
     * Execute the statement.
     *
     * @param array<string|int,mixed>|null $arguments The values for the statement's parameters.
     *
     * @return bool `true` if the statement was executed, `false` if not.
     */
    public function execute(?array $arguments = null): mixed;

    /**
     * Fetch all the rows from the last execution of the statement.
     *
     * @return array<int,array<string,mixed>> The rows.
     */
    public function fetchAll(): array;

    /**
     * Fetch the next row from the last execution of the statement.
     *
     * TODO should this also throw for statements that are not DQL
     *
     * @return array<string,mixed>|null The data for the next row, `null` if there are no rows left.
     * @throws StatementException if the statement has not been executed.
     */
    public function fetchNext(): ?array;

    /**
     * Count the number of rows in the result from the last execution of the statement.
     *
     * @return int The number of rows.
     * @throws StatementException if the statement is not a DQL statement or an error occurs.
     */
    public function count(): int;
}
