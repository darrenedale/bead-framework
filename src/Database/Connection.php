<?php

namespace Bead\Database;

use Bead\Contracts\Database\Column as DatabaseColumnContract;
use Bead\Contracts\Database\ColumnSize as DatabaseColumnSizeContract;
use Bead\Contracts\Database\Connection as DatabaseConnectionContract;
use Bead\Contracts\Database\Constraint as DatabaseConstraintContract;
use Bead\Contracts\Database\DecimalColumnSize as DatabaseDecimalColumnSizeContract;
use Bead\Contracts\Database\DefaultConstraint as DatabaseDefaultConstraintContract;
use Bead\Contracts\Database\ForeignKey as DatabaseForeignKeyContract;
use Bead\Contracts\Database\Index as DatabaseIndexContract;
use Bead\Contracts\Database\NullabilityConstraint as DatabaseNullabilityConstraintContract;
use Bead\Contracts\Database\QueryBuilder as QueryBuilderContract;
use Bead\Contracts\Database\Statement as DatabaseStatementContract;
use Bead\Contracts\Database\Table as DatabaseTableContract;
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

    public function prepare(string $sql): DatabaseStatementContractase
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

    public function insert(string $table, array $data, ?int $batchSize = null): int|string|null
    {
        if (0 === count($data)) {
            throw new InvalidArgumentException("Expected data to insert, found empty array");
        }

        $columns = null;

        foreach ($data as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("Expected array of data to insert, found " . get_type($row));
            }

            if (0 === count($row)) {
                throw new InvalidArgumentException("Expected data to insert, found empty array");
            }

            if (null === $columns) {
                $columns = array_keys($row);

                foreach ($columns as $column) {
                    if (!is_string($column)) {
                        throw new InvalidArgumentException("Expected string column name, found {$column}");
                    }

                    if ("" === $column) {
                        throw new InvalidArgumentException("Expected non-empty column name, found empty column name");
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
            throw new InvalidArgumentException("Expected batch siez >= 1, found {$batchSize}");
        }

        while (0 < count($data)) {
            $insert = array_splice($data, 0, $batchSize);
            $this->query(self::insertDdl($table, $insert));
        }

        return $this->insertId();
    }

    public function createTable(DatabaseTableContract $table): void
    {
        $this->query(self::createTableDdl($table));
    }

    public function renameTable(string $table, string $newName): void
    {
        $this->query(self::renameTableDdl($table, $newName));
    }

    public function dropTable(string $table): void
    {
        $this->query(self::dropTableDdl($table));
    }

    public function addColumn(string $table, DatabaseColumnContract $column): void
    {
        $this->query(self::addColumnDdl($table, $column));
    }

    public function modifyColumn(string $table, string $column, DatabaseColumnContract $newColumn): void
    {
        $this->query(self::modifyColumnDdl($able, $column, $newColumn));
    }

    public function renameColumn(string $table, string $column, string $newColumn): void
    {
        $this->query(self::renameColumnDdl($able, $column, $newColumn));
    }

    public function dropColumn(string $table, string $column): void
    {
        $this->query(self::dropColumnDdl($table, $column));
    }

    public function addPrimaryKey(string $table, DatabaseIndexContract $index): void
    {
        assert ($key->isPrimaryKey(), new LogicException("Expected primary key index"));
        $this->query(self::addPrimayKeyDdl($table, $index));
    }

    public function dropPrimaryKey(string $table): void
    {
        $this->query(self::dropPrimaryKeyDdl($table));
    }

    public function addIndex(string $table, DatabaseIndexContract $index): void
    {
        assert (!$key->isPrimaryKey(), new LogicException("Expected index, found primary key"));
        $this->query(self::addIndexDdl($table, $index));
    }

    public function renameIndex(string $table, string $index, string $newIndex): void
    {
        $this->query(self::renameIndexDdl($table, $index, $newIndex));
    }

    public function dropIndex(string $table, string $index): void
    {
        $this->query(self::dropIndexDdl($table, $index));
    }

    protected static function valueSql(mixed $value): string
    {
        return match (true) {
            is_string($value) => "'" . str_replace("'", "\\'", $value) . "\"",
            is_bool($value) => $value ? "1" : "0",
            $value instanceof DateTimeInterface => "'" . $value->format("Y-m-d H:i:s") . "'",
            null === $value => "NULL",
            default => "{$value}",
        };
    }

    protected static function insertDdl(string $table, array $data): string
    {
        assert (0 < count($data));

        $rowDdl = static fn (array $row): string => "(" . array_map([self::class, "valueSql"], $row) . ")";
        $columns = array_keys($data[0]);

        return "INSERT INTO `{$table}` (`" . implode("`, `", $columns) . "`) VALUES "
            . implode(", ", array_map($rowDdl, $row));
    }

    // TODO currently this is MySQL-specific, but we should abstract most of the operations in this class to backend
    //  adapters that produce backend-specific SQL
    protected static function createTableDdl(DatabaseTableContract $table): string
    {
        $ddl = "CREATE TABLE `{$table->name()}` (\n";
        $ddl .= implode(",\n", array_map([self::class, "columnDdl"], $table->columns()));
        $ddl .= ")\n";
        $ddl .= implode(",\n", array_map([self::class, "indexDdl"], $table->indices()));
        $ddl .= implode(",\n", array_map([self::class, "foreignKeyDdl"], $table->foreignKeys()));

        if (null !== $table->characterSet()) {
            $ddl .= self::characterSetDdl($table->characterSet());
        }

        if (null !== $table->collation()) {
            $ddl .= self::collationDdl($table->collation());
        }

        if (null !== $table->comment()) {
            $ddl .= self::commentDdl($table->comment());
        }

        return $ddl;
    }

    protected static function renameTableDdl(string $table, string $newTable): string
    {
        return "RENAME TABLE `{$table}` TO `{$newTable}`";
    }

    protected static function addColumnDdl(string $table, DatabaseColumnContract $column): string
    {
        return "ALTER TABLE `{$table}` ADD COLUMN " . self::columnDdl($column);
    }

    protected static function modifyColumnDdl(string $table, string $column, DatabaseColumnContract $newColumn): string
    {
        return "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` " . self::columnDdl($newColumn);
    }

    protected static function renameColumnDdl(string $table, string $column, string $newColumn): string
    {
        return "ALTER TABLE `{$table}` RENAME COLUMN `{$column}` TO `{$newColumn}`";
    }

    protected static function dropColumnDdl(string $table, string $column): string
    {
        return "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
    }

    protected static function addPrimayKeyDdl(string $able, DatabaseIndexContract $key): string
    {
        return "ALTER TABLE `{$table}` ADD " . self::indexDdl($key);
    }

    protected static function dropPrimaryKeyDdl(string $able): string
    {
        return "ALTER TABLE `{$table}` DROP PRIMARY KEY";
    }

    protected static function addIndexDdl(string $able, DatabaseIndexContract $key): string
    {
        return "ALTER TABLE `{$table}` ADD " . self::indexDdl($key);
    }

    protected static function renameIndexDdl(string $able, string $index, string $newIndex): string
    {
        return "ALTER TABLE `{$table}` RENAME INDEX `{$index}` TO `{$newIndex}`";
    }

    protected static function dropIndexDdl(string $able, string $index): string
    {
        return "ALTER TABLE `{$table}` DROP INDEX `{$index}`";
    }

    protected static function dropTableDdl(string $table): self
    {
        return "DROP TABLE `{$table}`";
    }

    protected static function columnTypeKeyword(int $type): string
    {
        return match ($type) {
            DatabaseColumnContract::TinyInteger => "TINYINT",
            DatabaseColumnContract::SmallInteger => "SMALLINT",
            DatabaseColumnContract::Integer => "INT",
            DatabaseColumnContract::BigIntegerInteger => "BIGINT",
            DatabaseColumnContract::UnsignedTinyInteger => "TINYINT UNSIGNED",
            DatabaseColumnContract::UnsignedSmallInteger => "SMALLINT UNSIGNED",
            DatabaseColumnContract::UnsignedInteger => "INT UNSIGNED",
            DatabaseColumnContract::UnsignedBigInteger => "BIGINT UNSIGNED",
            DatabaseColumnContract::Decimal => "DECIMAL",
            DatabaseColumnContract::Float => "FLOAT",
            DatabaseColumnContract::Double => "DOUBLE",
            DatabaseColumnContract::Varchar => "VARCHAR",
            DatabaseColumnContract::Char => "CHAR",
            DatabaseColumnContract::Text => "TEXT",
            DatabaseColumnContract::BigText => "LONGTEXT",
            DatabaseColumnContract::Date => "DATE",
            DatabaseColumnContract::Time => "TIME",
            DatabaseColumnContract::DateTime => "DATETIME",
            DatabaseColumnContract::VarBinary => "VARBINARY",
            DatabaseColumnContract::Binary => "BINARY",
            DatabaseColumnContract::Blob => "NBLOB",
            DatabaseColumnContract::BigBlob => "LONGBLOB",
            DatabaseColumnContract::Boolean => "BOOL",
            default => throw new RuntimeException("Expected valid column type, found {$type}"),
        };
    }

    protected static function columnConstraintDdl(DatabaseConstraintContract $constraint): string
    {
        return match ($constraint->type()) {
            DatabaseConstraintContract::Nullability
                => $constraint instanceof DatabaseNullabilityConstraintContract
                    ? $constraint->isNullable() ? "NULL" : "NOT NULL"
                    : throw new RuntimeException("Expected NullabilityConstraint, found {$constraint::class}"),
            DatabaseConstraintContract::Default
                => $constraint instanceof DatabaseDefaultConstraintContract
                    ? "DEFAULT " . match (true) {
                        is_string($constraint->defaultValue()) => "'{$constraint->defaultValue()}'",
                        is_bool($constraint->defaultValue()) => $constraint->defaultValue() ? 1 : 0,
                        null === $constraint->defaultValue() => "NULL",
                        default => $constraint->defaultValue(),
                    }
                    : throw new RuntimeException("Expected DefaultConstraint, found {$constraint::class}"),
            DatabaseConstraintContract::Unique => "UNIQUE",
            DatabaseConstraintContract::PrimaryKey => "PRIMARY KEY",
            DatabaseConstraintContract::AutoIncrement => "AUTO_INCREMENT",
            default => throw new RuntimeException("Expected valid constraint type, foudn {$constraint->type()}"),
        };
    }

    protected static function columnDdl(DatabaseColumnContract $column): string
    {
        $ddl = "`{$column->name()}` " . self::columnTypeKeyword($column->type());
        $size = $column->size();

        $ddl .= match ($column->type()) {
            DatabaseColumnContract::Char, DatabaseColumnContract::Varchar, DatabaseColumnContract::Binary, DatabaseColumnContract::VarBinary, DatabaseColumnContract::Text, DatabaseColumnContract::Blob
                => (null === $size ? throw new RuntimeException("Expected column size, found null") :"({$size->size()})"),
            DatabaseColumnContract::Decimal, DatabaseColumnContract::Double, DatabaseColumnContract::Float
                => match (true) {
                $size instanceof DatabaseDecimalColumnSizeContract => "({$size->size()},{$size->decimalPlaces()})",
                null === $size => throw new RuntimeException("Expected decimal column size, found null"),
                default => throw new RuntimeException("Expected decimal column size, found " . $size::class),
            },
            default => "",
        };

        foreach ($column->constraints() as $constraint) {
            $ddl .= self::columnConstraintDdl($constraint);
        }

        if (null !== $column->characterSet()) {
            $ddl .= self::characterSetDdl($column->characterSet());
        }

        if (null !== $column->collation()) {
            $ddl .= self::collationDdl($column->collation());
        }

        if (null !== $column->comment()) {
            $ddl .= self::commentDdl($column->comment());
        }

        return $ddl;
    }

    protected static function indexDdl(DatabaseIndexContract $index): string
    {
        $ddl = "";

        if ($index->isPrimaryKey()) {
            $ddl = "PRIMARY KEY ";
        } else {
            if ($index->isUnique()) {
                $ddl = "UNIQUE ";
            }

            $ddl .= "INDEX `{$index->name()}` ";
        }

        $ddl .= " (`" . implode("`, `", $index->columns())  . "`)";
        return $ddl;
    }

    protected static function foreignKeyDdl(DatabaseForeignKeyContract $key): string
    {
        $ddl = "FOREIGN KEY `{$key->name()}` (`" . implode("`, `", $key->columns()) . "`) REFERENCES {$key->foreignTable()} (`" . implode("`, `", $key->foreignColumns()) . "`)";

        $ddl .= match ($key->onUpdate()) {
            DatabaseForeignKeyContract::Unspecified => "",
            DatabaseForeignKeyContract::NoAction => " ON UPDATE NO ACTION",
            DatabaseForeignKeyContract::Restrict => " ON UPDATE RESTRICT",
            DatabaseForeignKeyContract::Cascade => " ON UPDATE CASCADE",
            DatabaseForeignKeyContract::SetNull => " ON UPDATE SET NULL",
            DatabaseForeignKeyContract::SetDefault => " ON UPDATE SET DEFAULT",
        };

        $ddl .= match ($key->onDelete()) {
            DatabaseForeignKeyContract::Unspecified => "",
            DatabaseForeignKeyContract::NoAction => " ON DELETE NO ACTION",
            DatabaseForeignKeyContract::Restrict => " ON DELETE RESTRICT",
            DatabaseForeignKeyContract::Cascade => " ON DELETE CASCADE",
            DatabaseForeignKeyContract::SetNull => " ON DELETE SET NULL",
            DatabaseForeignKeyContract::SetDefault => " ON DELETE SET DEFAULT",
        };

        return $ddl;
    }

    protected static function characterSetDdl(string $charset): string
    {
        return "CHARACTER SET `{$charset}`";
    }

    protected static function collationDdl(string $collation): string
    {
        return "COLLATE `{$collation}`";
    }

    protected static function commentDdl(string $comment): string
    {
        return "COMMENT '{$comment}'";
    }
}
