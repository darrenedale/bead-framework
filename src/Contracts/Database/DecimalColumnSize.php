<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface DecimalColumnSize
{
    public function decimalPlaces(): int;
}
