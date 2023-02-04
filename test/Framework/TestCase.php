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
use LogicException;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use ReflectionException;
use ReflectionMethod;

/**
 * Base class for test cases for the framework.
 */
abstract class TestCase extends PhpUnitTestCase
{
    /** @var array<string> Names of functions mocked using mockFunction() */
    private array $functionMocks = [];


    /** Subclasses that reimplement tearDown() must call the parent implementation. */
    public function tearDown(): void
    {
        // removeFunctionMock() alters the array, so we iterate over a copy
        foreach (array_keys($this->functionMocks) as $function) {
            $this->removeFunctionMock($function);
        }

        parent::tearDown();
    }

    public function mockFunction(string $function, mixed $return): void
    {
        if (array_key_exists($function, $this->functionMocks)) {
            $this->removeFunctionMock($function);
        }

        uopz_set_return($function, $return, $return instanceof Closure);
        $this->functionMocks[$function] = $return;
    }

    public function removeFunctionMock(string $function): void
    {
        if (!array_key_exists($function, $this->functionMocks)) {
            throw new LogicException("Attempt to remove mock for function '{$function}' that isn't mocked.");
        }

        if ($this->functionMocks[$function] !== uopz_get_return($function)) {
            throw new LogicException("Mock for function '{$function}' has been removed externally.");
        }

        unset($this->functionMocks[$function]);
        uopz_unset_return($function);
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
