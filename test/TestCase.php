<?php

declare(strict_types=1);

namespace Equit\Test;

use Equit\Test\Constraints\AttributeIsInt;

abstract class TestCase extends \PHPUnit\Framework\TestCase {
	public static function assertFlatArraysAreEquivalent(array $expected, array $actual, string $msg = ""): void {
		self::assertThat($actual, self::flatArrayIsEquivalent($expected), $msg);
	}

	public static function flatArrayIsEquivalent(array $expected): Constraints\FlatArrayIsEquivalent {
		return new Constraints\FlatArrayIsEquivalent($expected);
	}

	public static function assertAttributeIsInt(array $objectAndAttr, $msg = ""): void {
		self::assertThat($objectAndAttr, self::attributeIsInt(), $msg);
	}

	public static function attributeIsInt(): Constraints\AttributeIsInt {
		return new AttributeIsInt();
	}
}
