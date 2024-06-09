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
    public function insertId(): int|string|null;

    /**
     * Create a table in the database.
     *
     * @throws TODO Some form of database exception if the table can't be created.
     */
    public function createTable(Table $table): void;

    /**
     * Drop a table from the database.
     *
     * @param string $table The existing table to drop.
     *
     * @throws TODO Some form of database exception if the table can't be dropped.
     */
    public function dropTable(string $table): void;

    /**
     * Rename a table in the database.
     *
     * @param string $table The existing table to rename.
     * @param string $newName The new name for the table.
     * @throws TODO Some form of database exception if the table can't be dropped.
     */
    public function renameTable(string $table, string $newName): void;

    /** Add a columne to an existing table. */
    public function addColumn(string $table, Column $column): void;

    /** Alter the definition of a table column. */
    public function modifyColumn(string $table, string $column, Column $newColumn): void;

    /** Rename a table column. */
    public function renameColumn(string $table, string $column, string $newColumn): void;

    /** Drop a table column. */
    public function dropColumn(string $table, string $column): void;

    /** Add an index to an existing table. */
    public function addIndex(string $table, Index $index): void;

    /** Rename a table index. */
    public function renameIndex(string $table, string $index, string $newIndex): void;

    /** Drop a table index. */
    public function dropIndex(string $table, string $index): void;

    /** Drop a table index. */
    public function addPrimaryKey(string $table, Index $index): void;

    /** Drop a table index. */
    public function dropPrimaryKey(string $table): void;

    /**
     * @param string $table The name of the table to insert into.
     * @param array $data An array of associative arrays with the data to insert.
     */
    public function insert(string $table, array $data, int $batchSize = null): int|string|null;
}
