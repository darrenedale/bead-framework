<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface Index
{
    public function name(): string;

    /** @return string[] */
    public function columns(): array;

    public function isUnique(): bool;

    public function isPrimaryKey(): bool;
}
