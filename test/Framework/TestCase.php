<?php

declare(strict_types = 1);

namespace Equit\Test\Framework;

use Closure;
use Equit\Test\Framework\Constraints\AttributeIsInt;
use Equit\Test\Framework\Constraints\FlatArrayIsEquivalent;
use PHPUnit\Framework\Constraint\GreaterThan;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use ReflectionMethod;

abstract class TestCase extends PhpUnitTestCase
{
	/**
	 * Make a given private/protected (static) method on an object/class accessible.
	 *
	 * For non-static methods the object must be given; for static methods it must be the class name.
	 *
	 * @param object|string $obj The object or class.
	 * @param string $method The name of the protected or private method.
	 *
	 * @return \Closure A closure that can be called to invoke the method on the object.
	 * @throws \ReflectionException
	 */
	protected static function accessibleMethod($objOrClass, string $method): Closure
	{
		$reflector = new ReflectionMethod($objOrClass, $method);
		$reflector->setAccessible(true);

		if (is_string($objOrClass)) {
			return $reflector->getClosure();
		}

		return $reflector->getClosure($objOrClass);
	}

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
