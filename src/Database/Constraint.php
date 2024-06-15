<?php
declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\Constraint as ConstraintContract;

class Constraint implements ConstraintContract
{
    private int $type;

    protected function __construct(int $type)
    {
        $this->type = $type;
    }

    public static function nullability(bool $nullable): NullabilityConstraint
    {
        return new NullabilityConstraint($nullable);
    }

    public static function default(string|int|float|null $default): DefaultConstraint
    {
        return new DefaultConstraint($default);
    }

    public static function unique(): self
    {
        return new self(ConstraintContract::Unique);
    }

    public static function primaryKey(): self
    {
        return new self(ConstraintContract::PrimaryKey);
    }

    public static function autoIncrement(): self
    {
        return new self(ConstraintContract::AutoIncrement);
    }

    public function type(): int
    {
        return $this->type;
    }
}
