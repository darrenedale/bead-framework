<?php
declare(strict_types=1);

namespace Bead\Database\Adapters;

use Bead\Contracts\Database\ConnectionAdapter;
use Bead\Contracts\Database\Column as DatabaseColumnContract;
use Bead\Contracts\Database\Constraint as DatabaseConstraintContract;
use Bead\Contracts\Database\DecimalColumnSize as DatabaseDecimalColumnSizeContract;
use Bead\Contracts\Database\DefaultConstraint as DatabaseDefaultConstraintContract;
use Bead\Contracts\Database\ForeignKey as DatabaseForeignKeyContract;
use Bead\Contracts\Database\Index as DatabaseIndexContract;
use Bead\Contracts\Database\NullabilityConstraint as DatabaseNullabilityConstraintContract;
use Bead\Contracts\Database\Table as DatabaseTableContract;

class MySqlAdapter implements ConnectionAdapter
{
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

    /**
     * @inheritDoc
     */
    public function insertDdl(string $table, array $columns, int $rowCount): string
    {
        $placeholders = substr(str_repeat("(" . implode(",", array_fill(0, count($columns), "?")) . "),", $rowCount), 0, -1);
        $columns = "(`" . implode("`, `", $columns) . "`)";

        return "INSERT INTO `{$table}` {$columns} VALUES {$placeholders}";
    }

    public function hasTableSql(string $table): string
    {
        return <<<SQL
SELECT
    COUNT(TABLE_NAME)
FROM
    information_schema.TABLES
WHERE
    TABLE_SCHEMA = DATABASE() AND
    TABLE_TYPE LIKE 'BASE TABLE' AND
    TABLE_NAME = '{$table}'
SQL;
    }

    public function createTableDdl(DatabaseTableContract $table): string
    {
        $ddl = "CREATE TABLE `{$table->name()}` (\n";
        $ddl .= implode(",\n", array_map([$this, "columnDdl"], $table->columns()));
        $ddl .= ")\n";
        $ddl .= implode(",\n", array_map([$this, "indexDdl"], $table->indices()));
        $ddl .= implode(",\n", array_map([$this, "foreignKeyDdl"], $table->foreignKeys()));

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

    public function renameTableDdl(string $table, string $newTable): string
    {
        // TODO sanitise names
        return "RENAME TABLE `{$table}` TO `{$newTable}`";
    }

    public function addColumnDdl(string $table, DatabaseColumnContract $column): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` ADD COLUMN " . self::columnDdl($column);
    }

    public function modifyColumnDdl(string $table, string $column, DatabaseColumnContract $newColumn): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` " . self::columnDdl($newColumn);
    }

    public function renameColumnDdl(string $table, string $column, string $newColumn): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` RENAME COLUMN `{$column}` TO `{$newColumn}`";
    }

    public function dropColumnDdl(string $table, string $column): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
    }

    public function addPrimayKeyDdl(string $table, DatabaseIndexContract $key): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` ADD " . self::indexDdl($key);
    }

    public function dropPrimaryKeyDdl(string $table): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` DROP PRIMARY KEY";
    }

    public function addIndexDdl(string $table, DatabaseIndexContract $key): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` ADD " . self::indexDdl($key);
    }

    public function renameIndexDdl(string $table, string $index, string $newIndex): string
    {
        // TODO sanitise names
        return "ALTER TABLE `{$table}` RENAME INDEX `{$index}` TO `{$newIndex}`";
    }

    public function dropIndexDdl(string $table, string $index): string
    {
        return "ALTER TABLE `{$table}` DROP INDEX `{$index}`";
    }

    public function dropTableDdl(string $table): ConnectionAdapter
    {
        // TODO sanitise name
        return "DROP TABLE `{$table}`";
    }

    public function columnConstraintDdl(DatabaseConstraintContract $constraint): string
    {
        return match ($constraint->type()) {
            DatabaseConstraintContract::Nullability
            => $constraint instanceof DatabaseNullabilityConstraintContract
                ? $constraint->isNullable() ? "NULL" : "NOT NULL"
                : throw new RuntimeException("Expected NullabilityConstraint, found " . $constraint::class),
            DatabaseConstraintContract::Default
            => $constraint instanceof DatabaseDefaultConstraintContract
                ? "DEFAULT " . match (true) {
                    is_string($constraint->defaultValue()) => "'{$constraint->defaultValue()}'",
                    is_bool($constraint->defaultValue()) => $constraint->defaultValue() ? 1 : 0,
                    null === $constraint->defaultValue() => "NULL",
                    default => $constraint->defaultValue(),
                }
                : throw new RuntimeException("Expected DefaultConstraint, found " . $constraint::class),
            DatabaseConstraintContract::Unique => "UNIQUE",
            DatabaseConstraintContract::PrimaryKey => "PRIMARY KEY",
            DatabaseConstraintContract::AutoIncrement => "AUTO_INCREMENT",
            default => throw new RuntimeException("Expected valid constraint type, foudn {$constraint->type()}"),
        };
    }

    public function columnDdl(DatabaseColumnContract $column): string
    {
        // TODO sanitise name
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
            $ddl .= $this->columnConstraintDdl($constraint);
        }

        if (null !== $column->characterSet()) {
            $ddl .= $this->characterSetDdl($column->characterSet());
        }

        if (null !== $column->collation()) {
            $ddl .= $this->collationDdl($column->collation());
        }

        if (null !== $column->comment()) {
            $ddl .= $this->commentDdl($column->comment());
        }

        return $ddl;
    }

    public function indexDdl(DatabaseIndexContract $index): string
    {
        // TODO sanitise name
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

    public function foreignKeyDdl(DatabaseForeignKeyContract $key): string
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

    public function characterSetDdl(string $charset): string
    {
        // TODO sanitise charset
        return "CHARACTER SET `{$charset}`";
    }

    public function collationDdl(string $collation): string
    {
        // TODO sanitise collation
        return "COLLATE `{$collation}`";
    }

    public function commentDdl(string $comment): string
    {
        // TODO sanitise comment
        return "COMMENT '{$comment}'";
    }
}