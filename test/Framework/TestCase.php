<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

declare(strict_types = 1);

namespace Equit\Test\Framework;

use Closure;
use Equit\Test\Framework\Constraints\AttributeIsInt;
use Equit\Test\Framework\Constraints\FlatArrayIsEquivalent;
use PHPUnit\Framework\Constraint\GreaterThan;
use PHPUnit\Framework\Constraint\LessThan;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use ReflectionException;
use ReflectionMethod;

abstract class TestCase extends PhpUnitTestCase
{
	/**
	 * Make a given private/protected (static) method on an object/class accessible.
	 *
	 * For non-static methods the object must be given; for static methods it must be the class name.
	 *
	 * @param object|string $objOrClass The object or class.
	 * @param string $method The name of the protected or private method.
	 *
	 * @return Closure A closure that can be called to invoke the method on the object.
	 * @throws ReflectionException if the method does not exist.
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

    /**
     * Fetch a randomly-generated string.
     *
     * @param int $length The length, in bytes.
     * @param string $dictionary The optional dictionary from which to draw the characters. Defaults to alphanumeric
     * plus a bunch of reasonably safe symbols (all printable).
     *
     * @return string The random string.
     */
    public static function randomString(int $length, string $dictionary = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_=+[]{};:@<>,.!£\$%^&*()"): string
    {
        $str = "";

        while (0 < $length) {
            $str .= $dictionary[mt_rand(0, strlen($dictionary) - 1)];
            --$length;
        }

        return $str;
    }

    /**
     * Generate a somewhat random floating-point value.
     *
     * @return float The value.
     */
    public static function randomFloat(float $min = 0.0, float $max = 100.0): float
    {
        return $min + (lcg_value() * ($max - $min));
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
