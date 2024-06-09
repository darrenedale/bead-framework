<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface ColumnSize
{
    public function size(): int;
}
