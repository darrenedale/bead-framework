<?php

declare(strict_types = 1);

namespace BeadTests\Framework;

use ArrayAccess;
use Bead\Contracts\Email\Header as HeaderContract;
use Bead\Contracts\Email\Part as PartContract;
use BeadTests\Framework\Constraints\ArrayHasEntry;
use BeadTests\Framework\Constraints\AttributeIsInt;
use BeadTests\Framework\Constraints\Email\HasEquivalentHeader;
use BeadTests\Framework\Constraints\Email\HasEquivalentPart;
use BeadTests\Framework\Constraints\Email\HasHeader;
use BeadTests\Framework\Constraints\Email\HasPart;
use Closure;
use LogicException;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

use function uopz_get_return;
use function uopz_set_return;
use function uopz_unset_return;

/**
 * Base class for test cases for the framework.
 */
abstract class TestCase extends PhpUnitTestCase
{
    /** @var array<string,mixed> Functions mocked using mockFunction() */
    private array $functionMocks = [];

    /** @var array<string,array<string,mixed>> Methods mocked using mockMethod() */
    private array $methodMocks = [];

    public static function tempDir(): string
    {
        return "/tmp/bead-framework/test";
    }

    /** Subclasses that reimplement tearDown() must call the parent implementation. */
    public function tearDown(): void
    {
        foreach (array_keys($this->functionMocks) as $function) {
            $this->removeFunctionMock($function);
        }

        foreach (array_keys($this->methodMocks) as $class) {
            foreach (array_keys($this->methodMocks[$class]) as $method) {
                $this->removeMethodMock($class, $method);
            }

            unset ($this->methodMocks[$class]);
        }

        parent::tearDown();
    }

    /**
     * Replace a function with a mock.
     *
     * @param string $function The name of the function to replace.
     * @param mixed $return The return value or closure with which to replace the function.
     */
    public function mockFunction(string $function, mixed $return): void
    {
        if (array_key_exists($function, $this->functionMocks)) {
            $this->removeFunctionMock($function);
        }

        uopz_set_return($function, $return, $return instanceof Closure);
        $this->functionMocks[$function] = $return;
    }

    /**
     * Check whether a function is currently mocked.
     *
     * @param string $function The function to check.
     *
     * @return bool `true` if it's mocked, `false` if not.
     */
    public function isFunctionMocked(string $function): bool
    {
        return in_array($function, $this->functionMocks);
    }

    /**
     * Remove a function mock.
     *
     * @param string $function The name of the mocked function to restore.
     *
     * @throws LogicException if the provided function is not mocked.
     */
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
     * Replace a method with a mock.
     *
     * @param class-string $class The name of the class whose method is to be replaced.
     * @param string $method The name of the method to replace.
     * @param mixed $return The return value or closure with which to replace the method.
     */
    public function mockMethod(string $class, string $method, mixed $return): void
    {
        if (array_key_exists($class, $this->methodMocks) && array_key_exists($method, $this->methodMocks[$class])) {
            $this->removeMethodMock($class, $method);
        }

        if (!array_key_exists($class, $this->methodMocks)) {
            $this->methodMocks[$class] = [];
        }

        uopz_set_return($class, $method, $return, $return instanceof Closure);
        $this->methodMocks[$class][$method] = $return;
    }

    /**
     * Check whether a method is currently mocked.
     *
     * @param class-string $class The class whose method is to be checked.
     * @param string $method The method to check.
     *
     * @return bool `true` if it's mocked, `false` if not.
     */
    public function isMethodMocked(string $class, string $method): bool
    {
        return in_array($class, $this->methodMocks) && in_array($method, $this->methodMocks[$class]);
    }

    /**
     * Remove a method mock.
     *
     * @param class-string $class The name of the class whose mocked method is to be restored.
     * @param string $method The name of the mocked method to restore.
     *
     * @throws LogicException if the provided method is not mocked.
     */
    public function removeMethodMock(string $class, string $method): void
    {
        if (!array_key_exists($class, $this->methodMocks) || !array_key_exists($method, $this->methodMocks[$class])) {
            throw new LogicException("Attempt to remove mock for method '{$class}::{$method}' that isn't mocked.");
        }

        // strtolower() works around bug in old(er) versions of uopz
        if ($this->methodMocks[$class][$method] !== uopz_get_return(strtolower($class), strtolower($method))) {
            throw new LogicException("Mock for method '{$class}::{$method}' has been removed externally.");
        }

        unset($this->methodMocks[$class][$method]);
        uopz_unset_return($class, $method);
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

    /**
     * Assert that an array-like value contains a given entry.
     *
     * @param mixed $key The required key.
     * @param mixed $value The value required for the key.
     * @param array|ArrayAccess $arr The array-like value to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertArrayHasEntry(mixed $key, mixed $value, array|ArrayAccess $arr, string $msg = ""): void
    {
        self::assertThat($arr, new ArrayHasEntry($key, $value), $msg);
    }

    /**
     * Assert that an array-like value does not contain a given entry.
     *
     * @param mixed $key The key required to be absent or without the value.
     * @param mixed $value The value required not to be the value of the key.
     * @param array|ArrayAccess $arr The array-like value to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertArrayNotHasEntry(mixed $key, mixed $value, array|ArrayAccess $arr, string $msg = ""): void
    {
        self::assertThat($arr, new LogicalNot(new ArrayHasEntry($key, $value)), $msg);
    }

    /**
     * Assert that a Message or Part has a given Header instance.
     *
     * @param HeaderContract $header The header instance the Message or Part must have.
     * @param mixed $value The Message or Part to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertHasHeader(HeaderContract $header, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new HasHeader($header), $msg);
    }

    /**
     * Assert that a Message or Part does not have a given Header instance.
     *
     * @param HeaderContract $header The header instance the Message or Part must NOT have.
     * @param mixed $value The Message or Part to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertNotHasHeader(HeaderContract $header, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new LogicalNot(new HasHeader($header)), $msg);
    }

    /**
     * Assert that a Message or Part has a header equivalent to a given Header instance.
     *
     * @param HeaderContract $header The header instance the Message or Part must have an equivalent of.
     * @param mixed $value The Message or Part to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertHasEquivalentHeader(HeaderContract $header, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new HasEquivalentHeader($header), $msg);
    }

    /**
     * Assert that a Message or Part does not have a header equivalent to a given Header instance.
     *
     * @param HeaderContract $header The header instance the Message or Part must NOT have an equivalent of.
     * @param mixed $value The Message or Part to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertNotHasEquivalentHeader(HeaderContract $header, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new LogicalNot(new HasEquivalentHeader($header)), $msg);
    }

    /**
     * Assert that a Message has a given Part instance.
     *
     * @param PartContract $part The Part instance the Message must have.
     * @param mixed $value The Message to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertHasPart(PartContract $part, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new HasPart($part), $msg);
    }

    /**
     * Assert that a Message does not have a given Part instance.
     *
     * @param PartContract $part The Part instance the Message must NOT have.
     * @param mixed $value The Message to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertNotHasPart(PartContract $part, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new LogicalNot(new HasPart($part)), $msg);
    }

    /**
     * Assert that a Message has a part equivalent to a given Part instance.
     *
     * @param PartContract $part The Part instance the Message must have an equivalent of.
     * @param mixed $value The Message to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertHasEquivalentPart(PartContract $part, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new HasEquivalentPart($part), $msg);
    }

    /**
     * Assert that a Message does not have a part equivalent to given Part instance.
     *
     * @param PartContract $part The Part instance the Message must NOT have and equivalent of.
     * @param mixed $value The Message to test.
     * @param string $msg The message for when the assertion fails.
     */
    public static function assertNotHasEquivalentPart(PartContract $part, mixed $value, string $msg = ""): void
    {
        self::assertThat($value, new LogicalNot(new HasEquivalentPart($part)), $msg);
    }

    /**
     * Call this if the test is externally verified (e.g. by Mockery).
     *
     * This prevents PHPUnit from marking the test as risky on the basis that it doesn't perform any assertions.
     */
    protected static function markTestAsExternallyVerified(): void
    {
        self::assertTrue(true);
    }
}
