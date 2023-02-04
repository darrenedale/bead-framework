<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types = 1);

namespace BeadTests\Framework;

use Closure;
use BeadTests\Framework\Constraints\AttributeIsInt;
use BeadTests\Framework\Constraints\FlatArrayIsEquivalent;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use ReflectionException;
use ReflectionMethod;

/**
 * Base class for test cases for the framework.
 */
abstract class TestCase extends PhpUnitTestCase
{
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

    /**
     * Assert that an attribute of an object is an int type.
     *
     * @param array $objectAndAttr The object and attribute name.
     * @param string $msg The message if the constraint is not satisfied.
     */
    public static function assertAttributeIsInt(array $objectAndAttr, string $msg = ""): void
    {
        self::assertThat($objectAndAttr, new AttributeIsInt(), $msg);
    }
}
