<?php
declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\DecimalColumnSize as DecimalColumnSizeContract;
use RuntimeException;

class DecimalColumnSize extends ColumnSize implements DecimalColumnSizeContract
{
    private int $decimalPlaces;

    public function __construct(int $size, int $decimalPlaces)
    {
        self::checkDecimalPlaces($decimalPlaces);
        parent::__constuct($size);
        $this->decimalPlaces = $decimalPlaces;
    }

    private static function checkDecimalPlaces(int $decimalPlaces): void
    {
        if (0 > $decimalPlaces) {
            throw new RuntimeException("Expected decimal places >= 0, found {$decimalPlaces}");
        }
    }

    public function decimalPlaces(): int
    {
        return $this->decimalPlaces;
    }

    public function withDecimalPlaces(int $decimalPlaces): self
    {
        self::checkDecimalPlaces($decimalPlaces);
        $clone = clone $this;
        $clone->decimalPlaces = $decimalPlaces;
        return $clone;
    }
}
