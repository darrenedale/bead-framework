<?php

namespace Equit\Testing;

use Closure;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use RuntimeException;
use TypeError;

/**
 * Mock any PHP function.
 *
 * Create instances and install them to mock functions. Any function can be mocked any number of times. A stack is
 * maintained of mocks for each function. When a mock is installed it is placed on top of the stack of mocks for that
 * function and activated. If/when the mock is removed, the next mock for the function (i.e. the new top of the stack)
 * is activated, until there are no more mocks on the stack (at which point the original function is restored).
 *
 * Active mocks can be temporarily suspended without being removed. Call suspend()/resume() on the mock in question, or
 * call the static suspendMock() and resumeMock() method with the function name to suspend the currently active mock for
 * that function.
 *
 * By defualt, mocks must be compatible with the function they replace - parameter types and return type must agree, and
 * arguments passed at call time must be compatible with the original. These checks can be switched off for individual
 * mocks.
 *
 * TODO handle union/intersection types if runtime PHP version supports them (8+)
 * TODO need a strong unit test for this one...
 */
class MockFunction
{
    /* types of mock */
    private const UNDEFINED_TYPE = 0;
    private const STATIC_VALUE_TYPE = 1;
    private const REPLACEMENT_CLOSURE_TYPE = 2;
    private const MAPPED_VALUE_TYPE = 3;
    private const SEQUENCED_VALUE_TYPE = 4;

    /** @var array<string,MockFunction[]>  */
    private static array $m_mocks = [];

    /** @var ReflectionFunction The function being mocked. */
    private ReflectionFunction $m_function;

    /** @var int The type of mock being used. */
    private int $m_mockType;

    /** @var mixed|array|Closure The mock for the function. */
    private $m_mock;

    /** @var bool Whether to check the return type of the mock with the original when it's set. */
    private bool $m_checkReturnType = true;

    /** @var bool Whether to check the parameters of the mock for compatibility with the original when it's set. */
    private bool $m_checkReplacementParams = true;

    /** @var bool Whether to check the compatibility of the arguments with the original when the mock is called. */
    private bool $m_checkCallArgs = true;

    /**
     * Initialise a new function mock.
     *
     * @param string|null $functionName The optional name of the function to mock.
     */
    public function __construct(?string $functionName = null)
    {
        $this->m_mockType = self::UNDEFINED_TYPE;

        if (isset($functionName)) {
            $this->setName($functionName);
        }
    }

    /**
     * Fetch the name of the function being mocked.
     *
     * @return string The function name.
     */
    public function name(): string
    {
        return $this->m_function->getName();
    }

    /**
     * Fluently set the name of the function being mocked.
     *
     * @param string $name The function name.
     * @throws InvalidArgumentException if the named function does not exist.
     */
    public function setName(string $name): void
    {
        try {
            $this->m_function = new ReflectionFunction($name);
        } catch (\ReflectionException $err) {
            throw new InvalidArgumentException("Function {$name} is not defined.", $err);
        }
    }

    /**
     * Fluently set the name of the function being mocked.
     *
     * @param string $name The function name.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function named(string $name): self
    {
        $this->setName($name);
        return $this;
    }

    /**
     * Whether the parameter types will be checked for compatibility with the original when the mock is set.
     *
     * @return bool `true` if the parameter types will be checked, `false` if not.
     */
    public function willCheckParameters(): bool
    {
        return $this->m_checkReplacementParams;
    }

    /**
     * Set whether the parameter types will be checked for compatibility with the original when the mock is set.
     *
     * Setting this is not retrospective - if type checks were off when the mock was set, the mock will not be checked
     * unless it is re-set.
     *
     * @param bool $check `true` if the parameter types should be checked, `false` if not.
     */
    public function setCheckParameters(bool $check): void
    {
        $this->m_checkReplacementParams = $check;
    }

    /**
     * Fluently turn off parameter compatibility checks on the mock.
     *
     * Since MockFunction instances default to having parameter compatibility checks turned on, you must call this before
     * setting the mock if you want checks disabled.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function withoutParameterChecks(): self
    {
        $this->setCheckParameters(false);
        return $this;
    }

    /**
     * Fluently turn on parameter compatibility checks on the mock.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function withParameterChecks(): self
    {
        $this->setCheckParameters(true);
        return $this;
    }

    /**
     * Whether the return type will be checked for compatibility with the original when the mock is set.
     *
     * @return bool `true` if the return type will be checked, `false` if not.
     */
    public function willCheckReturnType(): bool
    {
        return $this->m_checkReturnType;
    }

    /**
     * Set whether the return type will be checked for compatibility with the original when the mock is set.
     *
     * Setting this is not retrospective - if the type check was off when the mock was set, the mock will not be checked
     * unless it is re-set.
     *
     * @param bool $check `true` if the return type should be checked, `false` if not.
     */
    public function setCheckReturnType(bool $check): void
    {
        $this->m_checkReplacementParams = $check;
    }

    /**
     * Fluently turn off return type compatibility checks on the mock.
     *
     * Since MockFunction instances default to having return type compatibility checks turned on, you must call this
     * before setting the mock if you want checks disabled.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function withoutReturnTypeCheck(): self
    {
        $this->setCheckReturnType(false);
        return $this;
    }

    /**
     * Fluently turn on the return type compatibility check on the mock.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function withReturnTypeCheck(): self
    {
        $this->setCheckParameters(true);
        return $this;
    }

    /**
     * Whether the arguments will be checked for compatibility with the original whenever the mock is called.
     *
     * @return bool `true` if call-time checks will be made, `false` if not.
     */
    public function willCheckArguments(): bool
    {
        return $this->m_checkCallArgs;
    }

    /**
     * Set whether the arguments will be checked for compatibility with the original when the mock is called.
     *
     * This will not affect any mock already active.
     *
     * @param bool $check `true` if the arguments should be checked, `false` if not.
     */
    public function setCheckArguments(bool $check): void
    {
        $this->m_checkReplacementParams = $check;
    }

    /**
     * Fluently turn off argument compatibility checks on the mock when called.
     *
     * Since MockFunction instances default to having argument compatibility checks turned on, you must call this
     * before activating the mock if you want checks disabled.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function withoutArgumentChecks(): self
    {
        $this->setCheckReturnType(false);
        return $this;
    }

    /**
     * Fluently turn on the argument compatibility check on the mock when called.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function withArgumentChecks(): self
    {
        $this->setCheckParameters(true);
        return $this;
    }

    /**
     * Fetch the type of a value.
     *
     * If it's an object, its class is returned; otherwise, the built-in type is returned. If the built-in type is
     * "integer" or "float", "number" is returned since even under strict_types PHP allows automatic casting between the
     * two, so for our purposes we consider them as type equivalents.
     *
     * @param mixed $value The value to inspect.
     *
     * @return string Its type.
     */
    protected static function typeOf($value): string
    {
        $actualType = (is_object($value) ? get_class($value) : gettype($value));

        if ("float" === $actualType || "integer" === $actualType) {
            return "number";
        }

        return $actualType;
    }

    /**
     * Floats and ints are freely interchangeable even under strict_types so remove the difference between them.
     *
     * This is use for type names taken from reflection types. These return "int" and "float" for int and float
     * introspected type declarations respectively. (Contrast this with what gettype() returns.)
     *
     * @param string|ReflectionNamedType $type The type to canonicalise.
     *
     * @return string The canonical type name.
     */
    protected static function canonicalReflectionTypeName($type): string
    {
        if ($type instanceof ReflectionType) {
            $type = $type->getName();
        }

        if ("float" === $type || "int" === $type) {
            return "number";
        }

        return $type;
    }

    static protected final function checkParameterEquality(ReflectionParameter $original, ReflectionParameter $replacement)
    {
        // NOTE no need to check optional: caller has already ensured the param counts and # of required params match

        if ($original->isPassedByReference() !== $replacement->isPassedByReference()) {
            throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the original and replacement must either both be passed by reference or both be passed by value.");
        }

        if ($original->isVariadic() !== $replacement->isVariadic()) {
            throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the original and replacement must either both be variadic or neither be variadic.");
        }

        // NOTE these checks aren't reliable on versions < 8.0 because the type system has some gaps
        if (8 <= PHP_MAJOR_VERSION) {
            if ($original->allowsNull() !== $replacement->allowsNull()) {
                throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the original and replacement should either both be nullable or both not be nullable.");
            }

            if ($original->hasType() !== $replacement->hasType()) {
                throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the replacement should match the type hint in the original.");
            }
        }

        if (!$original->hasType()) {
            return;
        }

        if ($original->getType()->getName() != $replacement->getType()->getName()) {
            throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the replacement have the same type as the original.");
        }
    }

    static protected final function checkReturnTypeEquality(ReflectionNamedType $original, ReflectionNamedType $replacement)
    {
        if ($original->getName() !== $replacement->getName()) {
            throw new InvalidArgumentException("The replacement return type does not match the original.");
        }

        if ($original->allowsNull() !== $replacement->allowsNull()) {
            throw new InvalidArgumentException("The replacement return type nullability does not match the original.");
        }
    }

    /**
     * Helper to check whether a closure can be used as a replacement for the function.
     *
     * @param Closure $replacement The replacement to check.
     *
     * @throws InvalidArgumentException if the closure cannot be used as a replacement.
     * @noinspection PhpDocMissingThrowsInspection ReflectionException is never thrown because the function name can't
     * be set to a function that doesn't exist.
     * @noinspection PhpUnhandledExceptionInspection ReflectionException is never thrown because the function name can't
     * be set to a function that doesn't exist.
     */
    protected function checkReplacementCompatibility(Closure $replacement): void
    {
        $replacement = new ReflectionFunction($replacement);

        if ($this->willCheckReturnType() && $this->m_function->hasReturnType()) {
            if (!$replacement->hasReturnType()) {
                throw new InvalidArgumentException("The original has a return type but the replacement does not.");
            }

            self::checkReturnTypeEquality($this->m_function->getReturnType(), $replacement->getReturnType());
        }

        if ($this->willCheckParameters()) {
            if ($replacement->getNumberOfParameters() !== $this->m_function->getNumberOfParameters()) {
                throw new InvalidArgumentException("The replacement function does not match the parameter count of the original function.");
            }

            if ($replacement->getNumberOfRequiredParameters() !== $this->m_function->getNumberOfRequiredParameters()) {
                throw new InvalidArgumentException("The replacement function does not match the required parameter count of the original function.");
            }

            $originalParams    = $this->m_function->getParameters();
            $replacementParams = $replacement->getParameters();

            for ($idx = 0; $idx < count($originalParams); ++$idx) {
                self::checkParameterEquality($originalParams[$idx], $replacementParams[$idx]);
            }
        }
    }

    /**
     * Set the mock to replace the mocked function with a closure.
     *
     * The closure's parameters must be compatible with those of the mocked function.
     *
     * @param Closure $replacement The replacement closure.
     *
     * @return $this The MockFunction instance for further method chaining.
     * @throws InvalidArgumentException if the closure's signature does not match the function it's replacing.
     */
    public function shouldBeReplacedWith(Closure $replacement): self
    {
        $this->checkReplacementCompatibility($replacement);

        $this->m_mockType = self::REPLACEMENT_CLOSURE_TYPE;
        $this->m_mock = $replacement;
        return $this;
    }

    /**
     * Set the mock to always return the same value.
     *
     * @param mixed $value The value to return.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function shouldReturn($value): self
    {
        if ($this->willCheckReturnType() && $this->m_function->hasReturnType()) {
            $expectedType = $this->m_function->getReturnType();

            if (!$expectedType->allowsNull() && is_null($value)) {
                throw new TypeError("Return type for mocked function {$this->name()} is not nullable but given null value to return.");
            }

            if (self::canonicalReflectionTypeName($expectedType) !== self::typeOf($value)) {
                throw new TypeError("Return type for mocked function {$this->name()} expected to be '{$expectedType}', found '" . self::typeOf($value) . "'.");
            }
        }

        $this->m_mockType = self::STATIC_VALUE_TYPE;
        $this->m_mock = $value;
        return $this;
    }

    /**
     * Set the mock to return a value from the provided map.
     *
     * The first argument in the call to the mocked function is used as the key into the map.
     *
     * @param array $map The map of return values.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function shouldReturnMappedValue(array $map): self
    {
        if (empty($map)) {
            throw new RuntimeException("Mock functions returning maps of values cannot use an empty map.");
        }

        if ($this->willCheckReturnType() && $this->m_function->hasReturnType()) {
            $expectedType = $this->m_function->getReturnType();
            $allowsNull = $expectedType->allowsNull();
            $expectedType = self::canonicalReflectionTypeName($expectedType);

            foreach ($map as $value) {
                if (!$allowsNull && is_null($value)) {
                    throw new TypeError("Return type for mocked function {$this->name()} is not nullable but given null value in map to return.");
                }

                if ($expectedType !== self::typeOf($value)) {
                    throw new TypeError("Return type for mocked function {$this->name()} expected to be '{$expectedType}', found '" . self::typeOf($value) . "' in map.");
                }
            }
        }

        $this->m_mockType = self::MAPPED_VALUE_TYPE;
        $this->m_mock = $map;
        return $this;
    }

    /**
     * Set the mock to return the values in an array in sequence.
     *
     * The items from the sequence are returned in order in consecutive calls to the mocked function. When the sequence
     * is exhausted, it begins again.
     *
     * @param array $sequence The sequence of values to return.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function shouldReturnSequence(array $sequence): self
    {
        if (empty($sequence)) {
            throw new RuntimeException("Mock functions returning sequences of values cannot use an empty sequence.");
        }

        if ($this->willCheckReturnType() && $this->m_function->hasReturnType()) {
            $expectedType = $this->m_function->getReturnType();
            $allowsNull = $expectedType->allowsNull();
            $expectedType = self::canonicalReflectionTypeName($expectedType);

            foreach ($sequence as $value) {
                if (!$allowsNull && is_null($value)) {
                    throw new TypeError("Return type for mocked function {$this->name()} is not nullable but given null value in sequence to return.");
                }

                if ($expectedType !== self::typeOf($value)) {
                    throw new TypeError("Return type for mocked function {$this->name()} expected to be '{$expectedType}', found '" . self::typeOf($value) . "' in sequence.");
                }
            }
        }

        $this->m_mockType = self::SEQUENCED_VALUE_TYPE;
        $this->m_mock = $sequence;
        return $this;
    }

    /**
     * Install the mock.
     *
     * The mock is added to the top of the function's stack of mocks and activated. If it's already in the stack, it's
     * promoted to the top and activated.
     */
    public function install(): void
    {
        self::installMock($this);
    }

    /**
     * Check whether the mock is installed.
     *
     * An installed mock may not necessarily be active.
     *
     * @return bool `true` if the mock is installed, `false` otherwise.
     */
    public final function isInstalled(): bool
    {
        return self::mockIsInstalled($this);
    }

    /**
     * Check whether the mock is top of the stack for its function.
     *
     * @return bool `true` if the mock is top of the function's stack of mocks, `false` otherwise.
     */
    public final function isTop(): bool
    {
        return self::mockIsTop($this);
    }

    /**
     * Check whether the mock is currently active.
     *
     * @return bool `true` if the mock is active, `false` otherwise.
     */
    public final function isActive(): bool
    {
        return self::mockIsActive($this);
    }

    /**
     * Remove the mock.
     *
     * The mock is removed from the stack. If it is currently active the next mock in the stack is activated.
     */
    public function remove(): void
    {
        self::removeMock($this);
    }

    /**
     * Suspend the mock.
     *
     * If the mock is currently active, it is suspended and the original function is restored.
     */
    public function suspend(): void
    {
        if (!$this->isActive()) {
            return;
        }

        self::suspendMock($this->name());
    }

    /**
     * Resume the mock.
     *
     * If the mock is top of the stack for the function it mocks and the mock for that funciton is currently suspended,
     * the function is re-mocked with this mock.
     */
    public function resume(): void
    {
        if (!$this->isTop()) {
            return;
        }

        self::resumeMock($this->name());
    }

    /**
     * Create a closure to double for the mocked function for a mock that returns a static value.
     *
     * @return Closure The closure.
     */
    protected final function createClosureForStaticValue(): Closure
    {
        $argChecker = new FunctionArgumentChecker($this->name());

        if (is_object($this->m_mock)) {
            $value = clone $this->m_mock;
        } else {
            $value = $this->m_mock;
        }

        return function(...$args) use ($value, $argChecker)
        {
            $argChecker->check(...$args);
            return $value;
        };
    }

    /**
     * Create a closure to double for the mocked function for a mock that returns a set of values in
     * sequence.
     *
     * @return Closure The closure.
     */
    protected final function createClosureForSequence(): Closure
    {
        $argChecker = new FunctionArgumentChecker($this->name());
        $values = $this->m_mock;

        return function(...$args) use ($values, $argChecker)
        {
            static $idx = 0;
            $argChecker->check(...$args);
            $ret = $values[$idx];
            $idx = ++$idx % count($values);
            return $ret;
        };
    }

    /**
     * Create a closure to double for the mocked function for a mock that is a replacement closure.
     *
     * TODO check closure args are compatible with original?
     *
     * @return Closure The closure.
     */
    protected final function createClosureForReplacementClosure(): Closure
    {
        $argChecker = new FunctionArgumentChecker($this->name());
        $fn = $this->m_mock;

        return function(...$args) use ($fn, $argChecker)
        {
            $argChecker->check(...$args);
            return $fn(...$args);
        };
    }

    /**
     * Create a closure to double for the mocked function for a mock that returns a value from a map.
     *
     * @return Closure The closure.
     */
    protected final function createClosureForMap(): Closure
    {
        $argChecker = new FunctionArgumentChecker($this->name());
        $values = $this->m_mock;

        return function(...$args) use ($values, $argChecker)
        {
            $argChecker->check(...$args);

            if (0 === count($args)) {
                throw new RuntimeException("Mock function with mapped return values must receive at least one argument.");
            }

            if (!isset($values[$args[0]])) {
                throw new RuntimeException("Mock function with mapped return values missing mapped value for provided argument.");
            }

            return $values[$args[0]];
        };
    }

    /**
     * Create the closure that will double for the mocked function.
     * @return Closure
     */
    protected final function createClosure(): Closure
    {
        switch ($this->m_mockType) {
            case self::UNDEFINED_TYPE:
                throw new LogicException("The type of mock is not defined - you must call one of the MockFunction::should...() methods before installing.");

            case self::REPLACEMENT_CLOSURE_TYPE:
                return $this->createClosureForReplacementClosure();

            case self::STATIC_VALUE_TYPE:
                return $this->createClosureForStaticValue();

            case self::SEQUENCED_VALUE_TYPE:
                return $this->createClosureForSequence();

            case self::MAPPED_VALUE_TYPE:
                return $this->createClosureForMap();
        }

        throw new LogicException("Unrecognised mock type {$this->m_mockType}.");
    }

    /**
     * Install a mock.
     *
     * A register is kept of each function mocked. For each function mocked a stack is built of the installed mocks for
     * that function.
     *
     * @param MockFunction $mock The mock to install.
     */
    public static function installMock(MockFunction $mock): void
    {
        $name = $mock->name();
        
        if (!self::mockIsInstalled($mock)) {
            // if it's not already on the stack, push it on
            if (!isset(self::$m_mocks[$name])) {
                self::$m_mocks[$name] = [$mock];
            } else {
                self::$m_mocks[$name][] = $mock;
            }
        } else {
            // if it's already on the stack, put it on top
            $idx = array_search($mock, self::$m_mocks[$name]);
            array_splice(self::$m_mocks[$name], $idx, 1);
            self::$m_mocks[$name][] = $mock;
        }

        uopz_set_return($name, $mock->createClosure(), true);
    }

    /**
     * Remove a mock from the stack for a function.
     *
     * The mock is removed from the stack. If it's currently active it is replaced with the next mock (i.e. the one that
     * is on top of the stack after the removal). If there are no more mocks on the stack for the function it is no
     * longer mocked.
     *
     * @param MockFunction $mock The mock to remove.
     *
     * @return void
     */
    public static function removeMock(MockFunction $mock): void
    {
        $name = $mock->name();
        $idx = array_search($mock, self::$m_mocks[$name] ?? []);

        if (false === $idx) {
            return;
        }

        array_splice(self::$m_mocks[$name], $idx, 1);

        // if the mock is currently active and the mock for the function is not suspended, activate the next mock on the
        // stack
        if (0 !== $idx && $idx === count(self::$m_mocks[$name]) && uopz_get_return($name)) {
            uopz_set_return($name, end(self::$m_mocks[$name])->createClosure(), true);
        } else {
            // if there are no more mocks on the stack for this function, remove it from the register
            if (empty(self::$m_mocks[$name])) {
                unset(self::$m_mocks[$name]);
            }

            uopz_unset_return($name);
        }
    }

    /**
     * Check whether a mock is installed.
     *
     * Note that a mock can be installed but not active or top of its stack.
     *
     * @param MockFunction $mock The mock to look for.
     *
     * @return bool `true` if the mock is in the stack for the function it doubles for, `false` if not.
     */
    public static function mockIsInstalled(MockFunction $mock): bool
    {
        return isset(self::$m_mocks[$mock->name()]) && in_array($mock, self::$m_mocks[$mock->name()]);
    }

    /**
     * Check whether a mock is installed and top of its stack.
     *
     * @param MockFunction $mock The mock to look for.
     *
     * @return bool `true` if the mock is on top of the stack for the function it doubles for, `false` if not.
     */
    public static function mockIsTop(MockFunction $mock): bool
    {
        // NOTE the register is guaranteed to be a non-empty array if it is set
        return isset(self::$m_mocks[$mock->name()]) && end(self::$m_mocks[$mock->name()]) === $mock;
    }

    /**
     * Check whether a mock is installed, top of its stack and currently active.
     *
     * @param MockFunction $mock The mock to look for.
     *
     * @return bool `true` if the mock is on top of the stack for the function it doubles for and is not suspended,
     * `false` if not.
     */
    public static function mockIsActive(MockFunction $mock): bool
    {
        return null !== uopz_get_return($mock->name()) && self::mockIsTop($mock);
    }

    /**
     * Suspend mocking of a named function.
     *
     * @param string $function The function for which to suspend mocking.
     */
    public function suspendMock(string $function): void
    {
        uopz_unset_return($function);
    }

    /**
     * Resume mocking of a named function.
     *
     * If the named function has no installed mocks an exception is thrown. Otherwise, the mock on top of the function's
     * stack is activated.
     *
     * @param string $function The function for which to resume mocking.
     */
    public function resumeMock(string $function): void
    {
        if (!isset(self::$m_mocks[$function])) {
            throw new RuntimeException("No mocks are installed for '{$function}'.");
        }

        uopz_set_return($function, end(self::$m_mocks[$function])->createClosure());
    }
}