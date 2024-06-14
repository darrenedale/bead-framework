<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

/**
 * Contract for adapters that provide Connection implementations with SQL for various operations.
 *
 * Adapters essentially abstract away differences in SQL dialects for different RDBMSes so that Connection
 * implementations don't need to care about whether it's accessing a MySQL or db/2 database.
 */
interface ConnectionAdapter
{
    /** Generate an INSERT DDL string with sufficient positional placeholders for a given number of rows of data. */
    public function insertDdl(string $table, array $columns, int $rowCount): string;

    /**
     * Generate some SQL to determine whether a table exists on the current schema.
     *
     * The SQL should return a single row with a single column. The value in the column should be 0 if the table does
     * not exist, non-0 if it does.
     */
    public function hasTableSql(string $table): string;

    public function createTableDdl(DatabaseTableContract $table): string;

    public function renameTableDdl(string $table, string $newTable): string;

    public function addColumnDdl(string $table, DatabaseColumnContract $column): string;

    public function modifyColumnDdl(string $table, string $column, DatabaseColumnContract $newColumn): string;

    public function renameColumnDdl(string $table, string $column, string $newColumn): string;

    public function dropColumnDdl(string $table, string $column): string;

    public function addPrimayKeyDdl(string $able, DatabaseIndexContract $key): string;

    public function dropPrimaryKeyDdl(string $able): string;

    public function addIndexDdl(string $able, DatabaseIndexContract $key): string;

    public function renameIndexDdl(string $able, string $index, string $newIndex): string;

    public function dropIndexDdl(string $able, string $index): string;

    public function dropTableDdl(string $table): self;


    public function columnConstraintDdl(DatabaseConstraintContract $constraint): string;

    public function columnDdl(DatabaseColumnContract $column): string;

    public function indexDdl(DatabaseIndexContract $index): string;

    public function foreignKeyDdl(DatabaseForeignKeyContract $key): string;

    public function characterSetDdl(string $charset): string;

    public function collationDdl(string $collation): string;

    public function commentDdl(string $comment): string;

}
