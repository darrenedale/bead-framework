<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface ForeignKey
{
    public const Unspecified = 0;

    public const NoAction = 1;

    public const Restrict = 2;

    public const Cascade = 3;

    public const SetNull = 4;

    public const SetDefault = 5;

    /** @return string The foreign key constraint name. */
    public function name(): string;

    /** @return string[] The columns on the table that reference the foreign table */
    public function columns(): array;

    /** @return string The foreign table referenced by the foreign key. */
    public function foreignTable(): string;

    /** @return string[] The columns on the foreign table that are referenced. */
    public function foreignColumns(): array;

    /** @return int The ON DELETE action when maintaining referential integrity. */
    public function onDelete(): int;

    /** @return int The ON UPDATE action when maintaining referential integrity. */
    public function onUpdate(): int;
}
