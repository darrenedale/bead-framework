<?php

namespace Bead\Database;

use Bead\Contracts\Database\Column as DatabaseColumnContract;
use Bead\Contracts\Database\ColumnSize as DatabaseColumnSizeContract;
use Bead\Contracts\Database\Connection as DatabaseConnectionContract;
use Bead\Contracts\Database\ConnectionAdapter;
use Bead\Contracts\Database\Constraint as DatabaseConstraintContract;
use Bead\Contracts\Database\DecimalColumnSize as DatabaseDecimalColumnSizeContract;
use Bead\Contracts\Database\DefaultConstraint as DatabaseDefaultConstraintContract;
use Bead\Contracts\Database\ForeignKey as DatabaseForeignKeyContract;
use Bead\Contracts\Database\Index as DatabaseIndexContract;
use Bead\Contracts\Database\NullabilityConstraint as DatabaseNullabilityConstraintContract;
use Bead\Contracts\Database\QueryBuilder as QueryBuilderContract;
use Bead\Contracts\Database\Statement as DatabaseStatementContract;
use Bead\Contracts\Database\Table as DatabaseTableContract;
use Bead\Database\Adapters\MySqlAdapter;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;
use phpDocumentor\Reflection\Types\Never_;
use RuntimeException;

use function Bead\Helpers\Iterable\all;

/**
 * Lightweight extension of PDO to implement the Connecton interface using PDO.
 */
class Connection implements DatabaseConnectionContract
{
    private ConnectionAdapter $adapter;

    private PDO $pdo;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        $this->pdo = new PDO($dsn, $username, $password, $options);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $driver = substr($dsn, 0, strpos($dsn, ":") ?: 0);

        $this->adapter = match ($driver) {
            "mysql" => new MySqlAdapter(),
            default => throw new RuntimeException("No adapter is available for {$driver} PDO driver"),
        };
    }

    /**
     * Escape any SQL wildcards found in some text.
     *
     * This helper function escapes a user-provided piece of text such that it can be safely used in a SQL _LIKE_ clause
     * without any characters provided by the user that have special meanings interfering with the results.
     *
     * @param $text string The text to escape.
     *
     * @return string The escaped text.
     */
    public static function escapeSqlWildcards(string $text): string
    {
        static $s_from = ["%", "_"];
        static $s_to = ["\\%", "\\_"];

        return str_replace($s_from, $s_to, $text);
    }

    /**
     * Translate from _de-facto_ to SQL wildcards.
     *
     * This helper function translates _*_ and _?_ in a user-provided piece of text to *%* and *_* respectively so that
     * it can be used in a SQL _LIKE_ clause with the intended meaning.
     *
     * @see sqlToDefactoWildcards()
     *
     * @param $text string The text to translate.
     *
     * @return string The translated text.
     */
    public static function defactoToSqlWildcards(string $text): string
    {
        static $s_from = ["*", "?"];
        static $s_to = ["%", "_"];

        return str_replace($s_from, $s_to, $text);
    }

    /**
     * Translate from _de-facto_ to SQL wildcards.
     *
     * This helper function translates _*_ and _?_ in a user-provided piece of text to _.*_ and _._ respectively so that
     * it can be used in a SQL _REGEX_ clause with the intended meaning.
     *
     * @param $text string The text to translate.
     *
     * @return string The translated text.
     */
    public static function defactoToRegExpWildcards(string $text): string
    {
        static $s_from = ["*", "?"];
        static $s_to = [".*", "."];

        return str_replace($s_from, $s_to, $text);
    }

    /**
     * Translate from SQL to *de-facto* wildcards.
     *
     * This helper function translates *%* and *_* in a user-provided piece of text to _*_ and _?_ respectively.
     *Type
     * @see defactoToSqlWildcards()
     *
     * @param $text string The text to translate.
     *
     * @return string The translated text.
     */
    public static function sqlToDefactoWildcards(string $text): string
    {
        static $s_from = ["%", "_"];
        static $s_to = ["*", "?"];

        return str_replace($s_from, $s_to, $text);
	}

    private function throwException(string $class, string $msg, PDOException $previous = null): void
    {
        if ($previous) {
            // PDOException::errorInfo is sometimes either not set or only partially set
            if (is_array($previous->errorInfo)) {
                $sqlCode = $previous->errorInfo[0] ?? "";
                $sqlMsg = $previous->errorInfo[2] ?? "";
            } else {
                $sqlCode = "";
                $sqlMsg = "";
            }

            $msg = "{$msg}: [{$previous->errorInfo[0]}] {$sqlMsg}";
        }

        throw new $class($msg, previous: $previous);
    }

    public function beginTransaction(): void
    {
        try {
            $success = $this->pdo->beginTransaction();
            $previous = null;
        } catch (PDOException $previous) {
            $success = false;
        }

        if (!$success) {
            // TODO decide what exception class this should be
            $this->throwException(RuntimeException::class, "Unable to begin transaction", $previous);
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function commitTransaction(): void
    {
        try {
            $success = $this->pdo->commit();
            $previous = null;
        } catch (PDOException $previous) {
            $success = false;
        }

        if (!$success) {
            // TODO decide what exception class this should be
            $this->throwException(RuntimeException::class, "Unable to commit transaction", $previous);
        }
    }

    public function rollBackTransaction(): void
    {
        try {
            $success = $this->pdo->rollBack();
            $previous = null;
        } catch (PDOException $previous) {
            $success = false;
        }

        if (!$success) {
            // TODO decide what exception class this should be
            $this->throwException(RuntimeException::class, "Unable to commit transaction", $previous);
        }
    }

    public function prepare(string $sql): DatabaseStatementContract
    {
        try {
            return new Statement($this->pdo->prepare($sql));
        } catch (PDOException $err) {
            // TODO decide what exception class this should be
            $this->throwException(RuntimeException::class, "Unable to prepare statement", $err);
        }
    }

    public function createQuery(): QueryBuilderContract
    {
        return new QueryBuilder($this);
    }

    public function lastInsertId(): int|string|null
    {
        try {
            $id = $this->pdo->lastInsertId();
        } catch (PDOException $err) {
            $id = false;
        }

        if (false === $id) {
            return null;
        }

        return $id;
    }

    /**
     * Caller is strongly encouraged to use the batch size parameter for large amounts of data.
     *
     * @param string $table
     * @param array $data
     * @param int|null $batchSize
     * @return int|string|null
     */
    public function insert(string $table, array $data, ?int $batchSize = null): int|string|null
    {
        if (0 === count($data)) {
            throw new InvalidArgumentException("Expected data to insert, found empty array");
        }

        $columns = null;

        foreach ($data as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("Expected row of data to insert, found " . gettype($row));
            }

            if (0 === count($row)) {
                throw new InvalidArgumentException("Expected row to insert, found empty array");
            }

            if (null === $columns) {
                $columns = array_keys($row);

                foreach ($columns as $column) {
                    if (!is_string($column)) {
                        throw new InvalidArgumentException("Expected string column name, found {$column}");
                    }

                    if ("" === $column) {
                        throw new InvalidArgumentException("Expected column name, found empty string");
                    }
                }
            } else {
                if ($columns !== array_keys($row)) {
                    throw new InvalidArgumentException("Expected column names '" . implode("', '", $columns) . "', found '" . implode("', '", array_keys($row)) . '"');
                }
            }
        }

        if (null === $batchSize) {
            $batchSize = count($data);
        } elseif (1 > $batchSize) {
            throw new InvalidArgumentException("Expected batch size >= 1, found {$batchSize}");
        }

        $ddl = $this->adapter->insertDdl($table, $columns, $batchSize);
        $stmt = $this->prepare($ddl);

        while (count($data) >= $batchSize) {
            // merge all the values from the next batch of rows into a signle array to bind to the statement
            $stmt->execute(flatten(array_splice($data, 0, $batchSize)));
        }

        // if the dataset size isn't a multiple of the batch size, insert the remainder
        if (0 < count($data)) {
            $ddl = $this->adapter->insertDdl($table, $columns, count($data));
            $stmt = $this->prepare($ddl);
            $stmt->execute(flatten($data));
        }

        return $this->lastInsertId();
    }

    public function delete(string $table, array $where = []): void
    {
        $ddl = $this->adapter->deleteDdl($table, $where);

        try {
            $stmt = $this->prepare($ddl);
            $stmt->execute(array_values($where));
        } catch (PDOException $err) {
            $this->throwException(RuntimeException::class, "Unable to delete records from {$table}");
        }
    }

    public function hasTable(string $table): bool
    {
        $stmt = $this->pdo->prepare($this->adapter->hasTableSql($table));
        $stmt->execute();
        $result = $stmt->fetchAll();

        if (!is_array($result) || 0 === count($result)) {
            return false;
        }

        $result = $result[0];

        if (!is_array($result) || 0 === count($result)) {
            return false;
        }

        $exists = filter_var(
            $result[0],
            FILTER_VALIDATE_INT,
            ["flags" => FILTER_NULL_ON_FAILURE,]
        ) ?? 0;

        return 0 !== $exists;
    }

    public function createTable(DatabaseTableContract $table): void
    {
        $this->pdo->query($this->adapter->createTableDdl($table));
    }

    public function renameTable(string $table, string $newName): void
    {
        $this->pdo->query($this->adapter->renameTableDdl($table, $newName));
    }

    public function dropTable(string $table): void
    {
        $this->pdo->query($this->adapter->dropTableDdl($table));
    }

    public function addColumn(string $table, DatabaseColumnContract $column): void
    {
        $this->pdo->query($this->adapter->addColumnDdl($table, $column));
    }

    public function modifyColumn(string $table, string $column, DatabaseColumnContract $newColumn): void
    {
        $this->pdo->query($this->adapter->modifyColumnDdl($able, $column, $newColumn));
    }

    public function renameColumn(string $table, string $column, string $newColumn): void
    {
        $this->pdo->query($this->adapter->renameColumnDdl($able, $column, $newColumn));
    }

    public function dropColumn(string $table, string $column): void
    {
        $this->pdo->query($this->adapter->dropColumnDdl($table, $column));
    }

    public function addPrimaryKey(string $table, DatabaseIndexContract $index): void
    {
        assert ($key->isPrimaryKey(), new LogicException("Expected primary key index"));
        $this->pdo->query($this->adapter->addPrimayKeyDdl($table, $index));
    }

    public function dropPrimaryKey(string $table): void
    {
        $this->pdo->query($this->adapter->dropPrimaryKeyDdl($table));
    }

    public function addIndex(string $table, DatabaseIndexContract $index): void
    {
        assert (!$key->isPrimaryKey(), new LogicException("Expected index, found primary key"));
        $this->pdo->query($this->adapter->addIndexDdl($table, $index));
    }

    public function renameIndex(string $table, string $index, string $newIndex): void
    {
        $this->pdo->query($this->adapter->renameIndexDdl($table, $index, $newIndex));
    }

    public function dropIndex(string $table, string $index): void
    {
        $this->pdo->query($this->adapter->dropIndexDdl($table, $index));
    }
}
