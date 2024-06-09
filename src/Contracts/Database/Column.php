<?php
declare(strict_types=1);

namespace Bead\Contracts\Database;

interface Column
{
    // integral types
    public const TinyInteger = 1;

    public const SmallInteger = 2;

    public const Integer = 3;

    public const BigInteger = 4;

    public const UnsignedTinyInteger = 5;

    public const UnsignedSmallInteger = 6;

    public const UnsignedInteger = 7;

    public const UnsignedBigInteger = 8;

    // real types
    public const Decimal = 50;

    public const Float = 51;

    public const Double = 52;

    // charater types
    public const Varchar = 100;

    public const Char = 101;

    public const Text = 102;

    public const BigText = 103;

    // calendar types
    public const Date = 150;

    public const Time = 151;

    public const DateTime = 152;

    // binary types
    public const VarBinary = 200;

    public const Binary = 201;

    public const Blob = 202;

    public const BigBlob = 203;

    // other types
    public const Boolean = 250;

    /** The column's name. */
    public function name(): string;

    /** One of the type constants. */
    public function type(): int;

    /** The column's size, if applicable. */
    public function size(): ?ColumnSize;

    /** @return Constraint[] */
    public function constraints(): array;

    /**
     * The column's character set, if applicable.
     *
     * This will be engine-specific.
     */
    public function characterSet(): ?string;

    /**
     * The column's collation, if applicable.
     *
     * This will be engine-specific.
     */
    public function collation(): ?string;

    /** The column's comment, if it has one. */
    public function comment(): ?string;
}
