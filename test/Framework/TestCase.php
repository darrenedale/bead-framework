<?php

declare(strict_types = 1);

namespace Equit\Test\Framework;

use Equit\Test\Framework\Constraints\AttributeIsInt;
use Equit\Test\Framework\Constraints\FlatArrayIsEquivalent;
use PHPUnit\Framework\Constraint\GreaterThan;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    public static function assertFlatArraysAreEquivalent(array $expected, array $actual, string $msg = ""): void
    {
        self::assertThat($actual, new FlatArrayIsEquivalent($expected), $msg);
    }

    public static function assertAttributeIsInt(array $objectAndAttr, $msg = ""): void
    {
        self::assertThat($objectAndAttr, new AttributeIsInt(), $msg);
    }

    public static function assertBetween($min, $max, $value, $msg = "")
    {
        self::assertThat($value, new GreaterThan($min), $msg);
        self::assertThat($value, new LessThan($max), $msg);
    }
}
