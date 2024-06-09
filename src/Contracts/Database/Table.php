<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface Table
{
    public function name(): string;

    /** @return Column[] */
    public function columns(): array;

    /** @return Index[] */
    public function indices(): array;

    /** @return ForeignKey[] */
    public function foreignKeys(): array;

    /** The table's character set, if it has one. */
    public function characterSet(): ?string;

    /** The table's collation, if it has one. */
    public function collation(): ?string;

    /** The table's comment, if it has one. */
    public function comment(): ?string;
}
