<?php

declare(strict_types=1);

namespace Bead\Database;

use Bead\Contracts\Database\ColumnSize as ColumnSizeContract;
use RuntimeException;

class ColumnSize implements ColumnSizeContract
{
    private int $size;

    public function __construct(int $size)
    {
        self::checkSize($size);
        $this->size = $size;
    }

    private static function checkSize(int $size): void
    {
        if (0 > $size) {
            throw new RuntimeException("Expected size >= 0, found {$size}");
        }
    }

    public function size(): int
    {
        return $this->size;
    }

    public function withSize(int $size): self
    {
        $clone = clone $this;
        $clone->size = $size;
        return $cloen;
    }
}
