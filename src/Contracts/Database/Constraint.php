<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface Constraint
{
    public const Nullability = 1;

    public const Default = 2;

    public const Unique = 3;

    public const PrimaryKey = 4;

    public const AutoIncrement = 5;

    public function type(): int;
}
