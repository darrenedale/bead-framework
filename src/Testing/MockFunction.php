<?php

declare(strict_types=1);

namespace Bead\Testing;

// NOTE use of ReflectionIntersectionType is guarded by PHP version check
use Bead\Exceptions\Testing\ExpectationNotSatisfiedException;
use Bead\Exceptions\Testing\NoMatchingExpectationException;
use Bead\Facades\Log;
use Closure;
use Error;
use InvalidArgumentException;
use LogicException;
use ReflectionException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use ReflectionType;
use RuntimeException;
use Throwable;
use TypeError;

/**
 * A Mockery-like class for mocking PHP functions or class methods with test expectations.
 */
final class MockFunction
{
    /** @var string|null The name of the class having a method mocked, if the mock is for a method. */
    private ?string $className = null;

    /** @var string The name of the function or method being mocked. */
    private string $functionName;

    /** @var Closure The mock for the function. */
    private Closure $replacementFunction;

    /** @var Expectation[] The expected calls. */
    private array $expectations = [];

    private int $callCount = 0;

    /** @var MockFunction[] The mocks installed from this class. */
    private static array $mocks = [];

    /**
     * Initialise a new function mock.
     *
     * Internal constructor - use the mock() and mockMethod() factory functions.
     */
    private function __construct()
    {
        /*
         * replacementFunction is called outside the context of this class (in uopz), so we wrap the call to
         * $this->handleCall() in another closure that's called in the context of this object (i.e. this constructor)
         * which means it can call the private method
         */
        $handleCall = fn(mixed ... $args) => $this->handleCall(... $args);
        $this->replacementFunction = fn(mixed ... $args) => $handleCall(... $args);
        self::$mocks[] = $this;
    }

    /**
     * Create a new function mock.
     *
     * Add test expectations to the returned mock by calling one of the methods that creates an Expectation and
     * configuring that Expectation to suit your requirements.
     *
     * Once successfully created, the function is entirely replaced with a mock that handles all calls and tracks your
     * expectations. The original operation of the function is entirely removed until you call close(). An internal
     * store of all mocks created is kept which means mocks you create will never be garbage collected until you call
     * close(), even if you don't retain a reference to the returned mock().
     *
     * You can't mock a function more than once. You can, however, mock a function again after you call close().
     *
     * @param string $functionName The name of the function to mock.
     *
     * @return self The mock.
     */
    public static function mock(string $functionName): self
    {
        $mock = new static();
        $mock->functionName = $functionName;

        // NOTE there's a bug in uopz where the fn name is lower-cased when stored in the hash table, but the name is
        //  not lower-cased when doing the lookup in uopz_get_return, so we work around that here
        $uopzFunctionName = mb_convert_case($functionName, MB_CASE_LOWER);

        if (null !== uopz_get_return($uopzFunctionName)) {
            throw new LogicException("A mock is already in place for {$functionName}");
        }

        uopz_set_return($uopzFunctionName, $mock->replacementFunction, true);
        return $mock;
    }

    /**
     * Create a new class method mock.
     *
     * Add test expectations to the returned mock by calling one of the methods that creates an Expectation and
     * configuring that Expectation to suit your requirements.
     *
     * Once successfully created, the method is entirely replaced with a mock that handles all calls and tracks your
     * expectations. The original operation of the function is entirely removed until you call close(). An internal
     * store of all mocks created is kept which means mocks you create will never be garbage collected until you call
     * close(), even if you don't retain a reference to the returned mock().
     *
     * You can't mock a method more than once. You can, however, mock a function again after you call close().
     *
     * @param class-string $className The name of the class whose method is to be mocked.
     * @param string $methodName The name of the method to mock.
     *
     * @return self The mock.
     */
    public static function mockMethod(string $className, string $methodName): self
    {
        $mock = new static();
        $mock->className = $className;
        $mock->functionName = $functionName;

        // NOTE there's a bug in uopz where the fn name is lower-cased when stored in the hash table, but the name is
        //  not lower-cased when doing the lookup in uopz_get_return, so we work around that here
        $uopzFunctionName = mb_strtolower($functionName, "UTF-8");

        if (null !== uopz_get_return($className, $uopzFunctionName)) {
            throw new LogicException("A mock is already in place for {$className}::{$functionName}");
        }

        uopz_set_return($className, $uopzFunctionName, $mock->replacementFunction, true);
        return $mock;
    }

    /**
     * End the mocking "session" and check that all expectations were satisfied for all mocks.
     *
     * @throws ExpectationNotSatisfiedException if one or more expectations were not met.
     */
    public static function close(): void
    {
        $exception = null;

        foreach (self::$mocks as $mock) {
            if (null === $exception) {
                try {
                    $mock->checkExpectations();
                } catch (ExpectationNotSatisfiedException $exception) {
                    // this is the exception that will be thrown, but we want to continue and uninstall all mocks before
                    // throwing it
                } catch (Throwable) {
                    // ignore other exceptions, we still need to uninstall all the remaining mocks
                }
            }

            self::uninstallMock($mock);
        }

        self::$mocks = [];

        if ($exception instanceof ExpectationNotSatisfiedException) {
            throw $exception;
        }
    }

    /**
     * Remove a mock function or method, and restore its original operation.
     *
     * @param MockFunction $mock The mock to uninstall.
     */
    private static function uninstallMock(MockFunction $mock): void
    {
        $uopzFunctionName = mb_strtolower($mock->functionName(), "UTF-8");

        if ($mock->isMethod()) {
            if ($mock->replacementFunction === uopz_get_return($mock->className(), $uopzFunctionName)) {
                uopz_unset_return($mock->className, $uopzFunctionName);
            }
        } else {
            if ($mock->replacementFunction === uopz_get_return($uopzFunctionName)) {
                uopz_unset_return($uopzFunctionName);
            }
        }
    }

    /**
     * Ultimately, this is the method that is called whenever the mocked function or method is invoked.
     *
     * @param mixed ...$args The function or method call arguments.
     *
     * @return mixed The value returned by the matching expecation.
     */
    private function handleCall(mixed ... $args): mixed
    {
        ++$this->callCount;
        $expectation = $this->matchExpectation(...$args);

        if (!$expectation instanceof Expectation) {
            throw new NoMatchingExpectationException("No matching expectation");
        }

        return $expectation->exec(...$args);
    }

    /**
     * Attempt to locate an Expectation that matches a set of arguments provided to the mocked function or method.
     *
     * @param mixed ...$args The function or method call arguments.#
     *
     * @return Expectation|null The matching Expectation, or null if there is no matching expectation.
     */
    private function matchExpectation(mixed ... $args): ?Expectation
    {
        foreach ($this->expectations as $expectation) {
            if (!$expectation->hasExpired() && $expectation->matches(...$args)) {
                return $expectation;
            }
        }

        return null;
    }

    /**
     * Check that all Expectations for the mock have been satisfied.
     *
     * @throws ExpectationNotSatisfiedException if any Expectation for the mock has not been satisfied.
     */
    private function checkExpectations(): void
    {
        foreach ($this->expectations as $expectation) {
            if (!$expectation->isSatisfied()) {
                if ($this->isMethod()) {
                    $function = "{$this->className()}::{$this->functionName()}";
                } else {
                    $function = $this->functionName();
                }

                throw new ExpectationNotSatisfiedException("Expectation that {$function}() {$expectation->description()} was not met.");
            }
        }
    }

    /**
     * Initialise a new expectation for the mock based on a set of arguments it should receive.
     *
     * The Expectation is added to the mock - existing expectations remain in place.
     *
     * @return Expectation The added expectation.
     */
    public function shouldBeCalledWith(mixed ... $arguments): Expectation
    {
        $expecation = Expectation::forArguments(... $arguments);
        $this->expectations[] = $expecation;
        return $expecation;
    }

    /**
     * Set an expectation that matches any call and returns a set value.
     *
     * Typically, you should only use this when you just want to mock the function and don't have any specific call
     * expectations. Setting an exception that matches any arguments is unlikely to work well alongside other argument-
     * specific expectations.
     */
    public function andReturn(mixed $value): Expectation
    {
        return Expectation::forAnyArguments()->andReturn($value);
    }

    /**
     * Set an expectation that matches any call and returns the result of calling a closure with thosee arguments.
     *
     * Typically, you should only use this when you just want to mock the function and don't have any specific call
     * expectations. Setting an exception that matches any arguments is unlikely to work well alongside other argument-
     * specific expectations.
     */
    public function andReturnUsing(callable $fn): Expectation
    {
        return Expectation::forAnyArguments()->andReturnUsing($fn);
    }

    /**
     * Set an expectation that matches any call and throws a given error.
     *
     * Typically, you should only use this when you just want to mock the function and don't have any specific call
     * expectations. Setting an exception that matches any arguments is unlikely to work well alongside other argument-
     * specific expectations.
     */
    public function andThrow(Throwable $error): Expectation
    {
        return Expectation::forAnyArguments()->andThrow($error);
    }

    /**
     * Fetch the name of the class whose method is being mocked.
     *
     * @return string|null The class name, or `null` if the mock is for a function not a method.
     */
    public function className(): ?string
    {
        return $this->className;
    }

    /**
     * Fetch the name of the function being mocked.
     *
     * @return string The function name.
     */
    public function functionName(): string
    {
        return $this->functionName;
    }

    /**
     * Check whether the mock is for a function.
     *
     * @return bool `true` if the mock is for a function, `false` otherwise.
     */
    public function isFunction(): bool
    {
        return !$this->isMethod();
    }

    /**
     * Check whether the mock is for a method.
     *
     * @return bool `true` if the mock is for a method, `false` otherwise.
     */
    public function isMethod(): bool
    {
        return null !== $this->className;
    }

    /**
     * How mamy times has the mock been called?
     *
     * @return int
     */
    public function callCount(): int
    {
        return $this->callCount;
    }
}
