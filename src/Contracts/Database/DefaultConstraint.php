<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface DefaultConstraint extends Constraint
{
    public function defaultValue(): string|int|float|bool|null;
}
