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
use RuntimeException;

use function Bead\Helpers\Iterable\all;

/**
 * Lightweight extension of PDO to implement the Connecton interface using PDO.
 */
class Connection extends PDO implements DatabaseConnectionContract
{
    private ConnectionAdapter $adapter;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);

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

    public function prepare(string $sql): DatabaseStatementContract
    {
        return new Statement(parent::prepare($sql));
    }

    public function createQuery(): QueryBuilderContract
    {
        return new QueryBuilder($this);
    }

    public function insertId(): int|string|null
    {
        try {
            $id = parent::lastInsertId();
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
                throw new InvalidArgumentException("Expected row of data to insert, found " . get_type($row));
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
            $stmt->execute(array_merge(...array_splice($data, 0, $batchSize)));
        }

        // if the dataset size isn't a multiple of the batch size, insert the remainder
        if (0 < count($data)) {
            $ddl = $this->adapter->insertDdl($table, $columns, count($data));
            $stmt = $this->prepare($ddl);
            $stmt->execute(array_merge(...$data));
        }

        return $this->insertId();
    }

    public function hasTable(string $table): bool
    {
        $result = $this->query($this->adapter->hasTableSql($table));

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
        $this->query($this->adapter->createTableDdl($table));
    }

    public function renameTable(string $table, string $newName): void
    {
        $this->query($this->adapter->renameTableDdl($table, $newName));
    }

    public function dropTable(string $table): void
    {
        $this->query($this->adapter->dropTableDdl($table));
    }

    public function addColumn(string $table, DatabaseColumnContract $column): void
    {
        $this->query($this->adapter->addColumnDdl($table, $column));
    }

    public function modifyColumn(string $table, string $column, DatabaseColumnContract $newColumn): void
    {
        $this->query($this->adapter->modifyColumnDdl($able, $column, $newColumn));
    }

    public function renameColumn(string $table, string $column, string $newColumn): void
    {
        $this->query($this->adapter->renameColumnDdl($able, $column, $newColumn));
    }

    public function dropColumn(string $table, string $column): void
    {
        $this->query($this->adapter->dropColumnDdl($table, $column));
    }

    public function addPrimaryKey(string $table, DatabaseIndexContract $index): void
    {
        assert ($key->isPrimaryKey(), new LogicException("Expected primary key index"));
        $this->query($this->adapter->addPrimayKeyDdl($table, $index));
    }

    public function dropPrimaryKey(string $table): void
    {
        $this->query($this->adapter->dropPrimaryKeyDdl($table));
    }

    public function addIndex(string $table, DatabaseIndexContract $index): void
    {
        assert (!$key->isPrimaryKey(), new LogicException("Expected index, found primary key"));
        $this->query($this->adapter->addIndexDdl($table, $index));
    }

    public function renameIndex(string $table, string $index, string $newIndex): void
    {
        $this->query($this->adapter->renameIndexDdl($table, $index, $newIndex));
    }

    public function dropIndex(string $table, string $index): void
    {
        $this->query($this->adapter->dropIndexDdl($table, $index));
    }
}
