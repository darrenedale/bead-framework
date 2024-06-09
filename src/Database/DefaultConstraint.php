<?php
declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\Constraint as ConstraintContract;
use Bead\Contracts\Database\DefaultConstraint as DefaultConstraintConstraint;
use Bead\Database\Constraint;

class DefaultConstraint extends Constraint implements DefaultConstraintConstraint
{
    private string|int|float|bool|null $default;

    public function __construct(string|int|float|bool|null $default)
    {
        parent::__construct(ConstraintContract::Default);
        $this->default = $default;
    }

    public function defaultValue(): string|int|float|bool|null
    {
        return $this->default;
    }

    public function withDefault(string|int|float|bool|null $default): self
    {
        $clone = clone $this;
        $clone->default = $default;
        return $clone;
    }
}
