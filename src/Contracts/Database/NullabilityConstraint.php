<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface NullabilityConstraint extends Constraint
{
    public function allowsNull(): bool;
}
