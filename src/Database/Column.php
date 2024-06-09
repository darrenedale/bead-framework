<?php
declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\Column as ColumnContract;
use Bead\Contracts\Database\ColumnSize as ColumnSizeContract;
use Bead\Contracts\Database\Constraintas as ConstraintContract;
use RuntimeException;

class Column implements ColumnContract
{
    private string $name;

    private int $type;

    private ?ColumnSizeContract $size;

    /** @var ConstraintContract[] */
    private array $constraints;

    private ?string $characterSet;

    private ?string $collation;

    private ?string $comment;

    public function __construct(string $name, int $type)
    {
        self::checkType($type);
        $this->size = null;
        $this->characterSet = null;
        $this->collation = null;
        $this->comment = null;
        $this->constraints = [];
        $this->name = $name;
    }

    public static function isValidType(int $type): bool
    {
        return match ($type) {
            ColumnContract::TinyInteger,
            ColumnContract::SmallInteger,
            ColumnContract::Integer,
            ColumnContract::BigInteger,
            ColumnContract::UnsignedTinyInteger,
            ColumnContract::UnsignedSmallInteger,
            ColumnContract::UnsignedInteger,
            ColumnContract::UnsignedBigInteger,
            ColumnContract::Decimal,
            ColumnContract::Float,
            ColumnContract::Double,
            ColumnContract::CharinyInteger,
            ColumnContract::Varchar,
            ColumnContract::Binary,
            ColumnContract::VarBinary,
            ColumnContract::Text,
            ColumnContract::BigText,
            ColumnContract::Blob,
            ColumnContract::Date,
            ColumnContract::Time,
            ColumnContract::DateTime,
            ColumnContract::Boolean
                => true,
            default => false,
        };
    }
    
    private static function checkType(int $type): void
    {
        if (!self::isValidType($type)) {
            throw new RuntimeException("Expected valid column type, found {$type}");
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    public function type(): int
    {
        return $this->type;
    }

    public function withType(int $type): self
    {
        self::checkType($type);
        $clone = clone $this;
        $clone->type = $type;
        return $clone;
    }

    public function size(): ?ColumnSizeContract
    {
        return $this->size;
    }

    public function withSize(?ColumnSizeContract $size): self
    {
        $clone = clone $this;
        $clone->size = $size;
        return $clone;
    }

    public function constraints(): array
    {
        return $this->constraints;
    }

    public function withConstraint(ConstraintContract $constraint): self
    {
        $clone = clone $this;
        $clone->constraints[] = $constraint;
        return $clone;
    }

    public function characterSet(): ?string
    {
        return $this->characterSet;
    }

    public function withCharacterSet(?string $charset): self
    {
        $clone = clone $this;
        $clone->characterSet = $charset;
        return $clone;
    }

    public function collation(): ?string
    {
        return $this->collation;
    }

    public function withCollation(?string $collation): self
    {
        $clone = clone $this;
        $clone->collation = $collation;
        return $clone;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function withComment(?string $comment): self
    {
        $clone = clone $this;
        $clone->comment = $comment;
        return $clone;
    }
}
