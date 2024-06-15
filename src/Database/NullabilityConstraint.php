<?php
declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\Constraint as ConstraintContract;
use Bead\Contracts\Database\NullabilityConstraint as NullabilityConstraintContract;

class NullabilityConstraint extends Constraint implements NullabilityConstraintContract
{
    private bool $nullable;

    public function __construct(bool $nullable)
    {
        parent::__construct(ConstraintContract::Nullability);
        $this->nullable = $nullable;
    }

    public function allowsNull(): bool
    {
        return $this->nullable;
    }

    public function withAllowsNull(bool $nullable): self
    {
        $clone = clone $this;
        $clone->nullable = $nullable;
        return $clone;
    }
}