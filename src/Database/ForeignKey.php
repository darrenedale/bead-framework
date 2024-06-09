<?php

declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\ForeignKey as ForeignKeyContract;
use RuntimeException;

class ForeignKey implements ForeignKeyContract
{
    /** @var string The name of the foriegn key constraint. */
    private string $name;

    /** @var string The foreign table to reference. */
    private string $foreignTable;

    /** @var string[] The columns that reference the foreign table. */
    private array $columns;

    /** @var string[] The columns on the foreign table that are referenecd. */
    private array $foreignColumns;

    /** @var int The action to take on deletion to maintain referential integrity. */
    private int $onDelete;

    /** @var int The action to take on update to maintain referential integrity. */
    private int $onUpdate;

    public function __construct(string $name, string $foreignTable, array $columns = [], array $foreignColumns = [])
    {
        $this->name = $name;
        $this->foreignTable = $foreignTable;
        $this->columns = $columns;
        $this->foreignColumns = $foreignColumns;
        $this->onDelete = ForeignKeyContract::Unspecified;
        $this->onUpdate = ForeignKeyContract::Unspecified;
    }

    private static function checkReferentialIntegrityAction(int $action): void
    {
        match ($action) {
            ForeignKeyContract::Unspecified,
            ForeignKeyContract::NoAction,
            ForeignKeyContract::Restrict,
            ForeignKeyContract::Cascade,
            ForeignKeyContract::SetNull,
            ForeignKeyContract::SetDefault
                => null,
            default => throw new RuntimeException("Expected valid referential integrity action, found {$action}"),
        };
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function withColumn(string $column): self
    {
        $clone = clone $this;
        $clone->columns[] = $column;
        return $clone;
    }

    public function foreignTable(): string
    {
        return $this->foreignTable;
    }

    public function withForeignTable(string $table): self
    {
        $clone = clone $this;
        $clone->foreignTable = $table;
        return $clone;
    }

    public function foreignColumns(): array
    {
        return $this->foreignColumns;
    }

    public function withForeignColumn(string $column): self
    {
        $clone = clone $this;
        $clone->foreignColumns[] = $column;
        return $clone;
    }

    public function onDelete(): int
    {
        return $this->onUpdate;
    }

    public function withDeleteAction(int $action): self
    {
        self::checkReferentialIntegrityAction($action);
        $clone = clone $this;
        $clone->onDelete = $action;
        return $clone;
    }

    public function onUpdate(): int
    {
        return $this->onUpdate;
    }

    public function withUpdateAction(int $action): self
    {
        self::checkReferentialIntegrityAction($action);
        $clone = clone $this;
        $clone->onUpdate = $action;
        return $clone;
    }
}