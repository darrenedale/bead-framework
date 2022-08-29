<?php

namespace Equit\Testing;

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
use TypeError;

/**
 * Mock any PHP function or method.
 *
 * Create and install instances to mock functions. Any function/method can be mocked any number of times. A stack is
 * maintained of mocks for each function/method. When a mock is installed it is placed on top of the stack of mocks for
 * that function/method and activated. If/when the mock is removed, the next mock for the function/method (i.e. the new
 * top of the stack) is activated, until there are no more mocks on the stack (at which point the original function/
 * method is restored).
 *
 * Active mocks can be temporarily suspended without being removed. Call suspend()/resume() on the mock in question, or
 * call the static suspendMock() and resumeMock() method with the function name or class and method names to suspend the
 * currently active mock for that function/method.
 *
 * By default, mocks must be compatible with the function/method they replace - parameter types and return type must
 * agree, and arguments passed at call time must be compatible with the original. These checks can be switched off for
 * individual mocks.
 *
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

    /** @var ReflectionFunction|ReflectionMethod The function being mocked. */
    private $m_functionReflector;

    /** @var int The type of mock being used. */
    private int $m_mockType = self::UNDEFINED_TYPE;

    /** @var mixed|array|Closure The mock for the function. */
    private $m_mock = null;

    /** @var bool Whether to check the return type of the mock with the original when it's set. */
    private bool $m_checkReturnType = true;

    /** @var bool Whether to check the parameters of the mock for compatibility with the original when it's set. */
    private bool $m_checkReplacementParams = true;

    /** @var bool Whether to check the compatibility of the arguments with the original when the mock is called. */
    private bool $m_checkCallArgs = true;

    /**
     * Initialise a new function mock.
     *
     * @param string|null $functionOrClassName The optional name of the class or function to mock.
     * @param string|null $methodName The optional name of the method to mock.
     */
    public function __construct(?string $functionOrClassName = null, ?string $methodName = null)
    {
        if (isset($methodName)) {
            $this->setMethod($functionOrClassName, $methodName);
        } else if (isset($functionOrClassName)) {
            $this->setFunctionName($functionOrClassName);
        }
    }

    /**
     * Fetch the name of the function being mocked.
     *
     * @return string The function name.
     */
    public function functionName(): string
    {
        if (!isset($this->m_functionReflector)) {
            throw new LogicException("Mock has not been initialised with a function or method to mock.");
        }

        return $this->m_functionReflector->getName();
    }

    /**
     * Fluently set the name of the function being mocked.
     *
     * @param string $name The function name.
     * @throws InvalidArgumentException if the named function does not exist.
     */
    public function setFunctionName(string $name): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException("Cannot change the method/function of a mock that is installed. Call remove() first.");
        }

        try {
            $this->m_functionReflector = new ReflectionFunction($name);
        } catch (ReflectionException $err) {
            throw new InvalidArgumentException("Function {$name} is not defined.", 0, $err);
        }
    }

    /**
     * Fluently set the name of the function being mocked.
     *
     * @param string $name The function name.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function forFunction(string $name): self
    {
        $this->setFunctionName($name);
        return $this;
    }

    /**
     * Fetch the name of the class whose method is being mocked.
     *
     * @return string|null The class name, or `null` if the mock is for a function not a method.
     */
    public function className(): ?string
    {
        if (!isset($this->m_functionReflector)) {
            throw new LogicException("Mock has not been initialised with a function or method to mock.");
        }

        if ($this->m_functionReflector instanceof ReflectionMethod) {
            return $this->m_functionReflector->getDeclaringClass()->getName();
        }

        return null;
    }

    /**
     * Fluently set the name of the function being mocked.
     *
     * @param string $className The class name.
     * @param string $methodName The name of the method to mock.
     * @throws InvalidArgumentException if the class does not exist or does not have the named method.
     */
    public function setMethod(string $className, string $methodName): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException("Cannot change the method/function of a mock that is installed. Call remove() first.");
        }

        try {
            $this->m_functionReflector = new ReflectionMethod($className, $methodName);
        } catch (ReflectionException $err) {
            throw new InvalidArgumentException("Method {$className}::{$methodName} is not defined.", 0, $err);
        }
    }

    /**
     * Fluently set the method being mocked.
     *
     * @param string $className The class of the method being mocked.
     * @param string $methodName The method being mocked.
     *
     * @return $this The MockFunction instance for further method chaining.
     * @throws InvalidArgumentException if the class or method does not exist.
     */
    public function forMethod(string $className, string $methodName): self
    {
        $this->setMethod($className, $methodName);
        return $this;
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
        return $this->m_functionReflector instanceof ReflectionMethod;
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
        $this->m_checkReturnType = $check;
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
        $this->setCheckReturnType(true);
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
        $this->m_checkCallArgs = $check;
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
        $this->setCheckArguments(false);
        return $this;
    }

    /**
     * Fluently turn on the argument compatibility check on the mock when called.
     *
     * @return $this The MockFunction instance for further method chaining.
     */
    public function withArgumentChecks(): self
    {
        $this->setCheckArguments(true);
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

    /**
     * @param ReflectionUnionType|ReflectionIntersectionType $original
     * @param ReflectionUnionType|ReflectionIntersectionType $replacement
     */
    static protected final function checkCompositeTypeEquality($original, $replacement): void
    {
        $originalTypes = $original->getTypes();
        $replacementTypes = $replacement->getTypes();

        $typeName = fn(ReflectionNamedType $type) => $type->getName();

        if (
            count($originalTypes) !== count($replacementTypes) ||
            count($originalTypes) !== count(array_intersect(
                array_map($typeName, $originalTypes),
                array_map($typeName, $replacementTypes)
            ))) {
            throw new Error("does not match original.");
        }
    }

    static protected final function checkParameterEquality(ReflectionParameter $original, ReflectionParameter $replacement): void
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

        $originalType = $original->getType();
        $replacementType = $replacement->getType();

        if (8 <= PHP_MAJOR_VERSION) {
            if (($originalType instanceof ReflectionUnionType) !== ($replacementType instanceof ReflectionUnionType)) {
                throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the replacement should be a union type if the original is.");
            }

            $performCompositeTypeCheck = ($originalType instanceof ReflectionUnionType);

            if (80100 <= PHP_VERSION_ID) {
                if (($originalType instanceof ReflectionUnionType) !== ($replacementType instanceof ReflectionUnionType)) {
                    throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the replacement should be an intersection type if the original is.");
                }

                $performCompositeTypeCheck = ($originalType instanceof ReflectionIntersectionType);
            }

            if ($performCompositeTypeCheck) {
                try {
                    self::checkCompositeTypeEquality($originalType, $replacementType);
                } catch (Error $err) {
                    throw new InvalidArgumentException("Parameter #{$original->getPosition()} composite type {$err->getMessage()}.");
                }

                return;
            }
        }

        if ($original->getType()->getName() != $replacement->getType()->getName()) {
            throw new InvalidArgumentException("Parameter #{$original->getPosition()} in the replacement should have the same type as the original.");
        }
    }

    static protected final function checkReturnTypeEquality(ReflectionNamedType $original, ReflectionNamedType $replacement)
    {
        // TODO composite types

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

        if ($this->willCheckReturnType() && $this->m_functionReflector->hasReturnType()) {
            if (!$replacement->hasReturnType()) {
                throw new InvalidArgumentException("The original has a return type but the replacement does not.");
            }

            self::checkReturnTypeEquality($this->m_functionReflector->getReturnType(), $replacement->getReturnType());
        }

        if ($this->willCheckParameters()) {
            if ($replacement->getNumberOfParameters() !== $this->m_functionReflector->getNumberOfParameters()) {
                throw new InvalidArgumentException("The replacement function does not match the parameter count of the original function.");
            }

            if ($replacement->getNumberOfRequiredParameters() !== $this->m_functionReflector->getNumberOfRequiredParameters()) {
                throw new InvalidArgumentException("The replacement function does not match the required parameter count of the original function.");
            }

            $originalParams    = $this->m_functionReflector->getParameters();
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
        if ($this->willCheckReturnType() && $this->m_functionReflector->hasReturnType()) {
            $expectedType = $this->m_functionReflector->getReturnType();

            if (!$expectedType->allowsNull() && is_null($value)) {
                throw new TypeError("Return type for mocked function {$this->functionName()} is not nullable but given null value to return.");
            }

            if (self::canonicalReflectionTypeName($expectedType) !== self::typeOf($value)) {
                throw new TypeError("Return type for mocked function {$this->functionName()} expected to be '{$expectedType}', found '" . self::typeOf($value) . "'.");
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

        if ($this->willCheckReturnType() && $this->m_functionReflector->hasReturnType()) {
            $expectedType = $this->m_functionReflector->getReturnType();
            $allowsNull = $expectedType->allowsNull();
            $expectedType = self::canonicalReflectionTypeName($expectedType);

            foreach ($map as $value) {
                if (!$allowsNull && is_null($value)) {
                    throw new TypeError("Return type for mocked function {$this->functionName()} is not nullable but given null value in map to return.");
                }

                if ($expectedType !== self::typeOf($value)) {
                    throw new TypeError("Return type for mocked function {$this->functionName()} expected to be '{$expectedType}', found '" . self::typeOf($value) . "' in map.");
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

        if ($this->willCheckReturnType() && $this->m_functionReflector->hasReturnType()) {
            $expectedType = $this->m_functionReflector->getReturnType();
            $allowsNull = $expectedType->allowsNull();
            $expectedType = self::canonicalReflectionTypeName($expectedType);

            foreach ($sequence as $value) {
                if (!$allowsNull && is_null($value)) {
                    throw new TypeError("Return type for mocked function {$this->functionName()} is not nullable but given null value in sequence to return.");
                }

                if ($expectedType !== self::typeOf($value)) {
                    throw new TypeError("Return type for mocked function {$this->functionName()} expected to be '{$expectedType}', found '" . self::typeOf($value) . "' in sequence.");
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

        if ($this->isMethod()) {
            self::suspendMock($this->className(), $this->functionName());
        } else {
            self::suspendMock($this->functionName());
        }
    }

    /**
     * Resume the mock.
     *
     * If the mock is top of the stack for the function it mocks and the mock for that funciton is currently suspended,
     * the function is re-mocked with this mock.
     */
    public function resume(): void
    {
        if ($this->isActive() || !$this->isTop()) {
            return;
        }

        if ($this->isMethod()) {
            self::resumeMock($this->className(), $this->functionName());
        } else {
            self::resumeMock($this->functionName());
        }
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
        return isset($this->m_functionReflector) && self::mockIsInstalled($this);
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

    protected function createArgumentChecker(): FunctionArgumentChecker
    {
        if ($this->isMethod()) {
            return new FunctionArgumentChecker($this->className(), $this->functionName());
        }

        return new FunctionArgumentChecker($this->functionName());
    }

    /**
     * Create a closure to double for the mocked function for a mock that returns a static value.
     *
     * @return Closure The closure.
     */
    protected final function createClosureForStaticValue(): Closure
    {
        if (is_object($this->m_mock)) {
            $value = clone $this->m_mock;
        } else {
            $value = $this->m_mock;
        }

        if ($this->willCheckArguments()) {
            $argChecker = $this->createArgumentChecker();

            return function (...$args) use ($value, $argChecker) {
                $argChecker->check(...$args);
                return $value;
            };
        }

        return function (...$args) use ($value) {
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
        $values = $this->m_mock;

        if ($this->willCheckArguments()) {
            $argChecker = $this->createArgumentChecker();

            return function (...$args) use ($values, $argChecker) {
                static $idx = 0;
                $argChecker->check(...$args);
                $ret = $values[$idx];
                $idx = ++$idx % count($values);
                return $ret;
            };
        }

        return function (...$args) use ($values) {
            static $idx = 0;
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
        $fn = $this->m_mock;

        if ($this->willCheckArguments()) {
            $argChecker = $this->createArgumentChecker();

            return function (...$args) use ($fn, $argChecker) {
                $argChecker->check(...$args);
                return $fn(...$args);
            };
        }

        return function (...$args) use ($fn) {
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
        $values = $this->m_mock;

        if ($this->willCheckArguments()) {
            $argChecker = $this->createArgumentChecker();

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

        return function(...$args) use ($values)
        {
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
     * Create the closure that will double for the mocked function/method.
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
     * Get the key into the register of installed mocks for a mock.
     *
     * @param string $functionOrClass The function name, or class name if it's a method mock.
     * @param string|null $methodName The method name if it's a method mock, or null if it's a function mock.
     *
     * @return string The key.
     */
    private static function keyForClassAndFunction(string $functionOrClass, ?string $methodName = null): string
    {
        if (isset($methodName)) {
            return "{$functionOrClass}::{$methodName}";
        }

        return $functionOrClass;
    }

    /**
     * Get the key into the register of installed mocks for a mock.
     *
     * @param MockFunction $mock The mock whose key is sought.
     *
     * @return string The key.
     */
    private static function keyForMock(MockFunction $mock): string
    {
        $className = $mock->className();

        if (isset($className)) {
            return self::keyForClassAndFunction($className, $mock->functionName());
        }

        return self::keyForClassAndFunction($mock->functionName());
    }

    /**
     * Helper to activate a mock.
     *
     * @param MockFunction $mock
     */
    private static function activateMock(MockFunction $mock): void
    {
        if ($mock->isMethod()) {
            uopz_set_return($mock->className(), $mock->functionName(), $mock->createClosure(), true);
        } else {
            uopz_set_return($mock->functionName(), $mock->createClosure(), true);
        }
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
        $key = self::keyForMock($mock);
        
        if (!self::mockIsInstalled($mock)) {
            // if it's not already on the stack, push it on
            if (!isset(self::$m_mocks[$key])) {
                self::$m_mocks[$key] = [$mock];
            } else {
                self::$m_mocks[$key][] = $mock;
            }
        } else {
            // if it's already on the stack, put it on top
            $idx = array_search($mock, self::$m_mocks[$key]);
            array_splice(self::$m_mocks[$key], $idx, 1);
            self::$m_mocks[$key][] = $mock;
        }

        self::activateMock($mock);
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
        $key = self::keyForMock($mock);
        $idx = array_search($mock, self::$m_mocks[$key] ?? []);

        if (false === $idx) {
            return;
        }

        // if the mock is currently active and there are other mocks on the stack, activate the next one on removal
        $activateNextMock = $mock->isActive() && 0 !== $idx;
        array_splice(self::$m_mocks[$key], $idx, 1);

        // if the mock is currently active and the mock for the function is not suspended, activate the next mock on the
        // stack
        if ($activateNextMock) {
            self::activateMock(end(self::$m_mocks[$key]));
        } else {
            // if there are no more mocks on the stack for this function, remove it from the register
            if (empty(self::$m_mocks[$key])) {
                unset(self::$m_mocks[$key]);
            }

            if ($mock->isMethod()) {
                uopz_unset_return($mock->className(), $mock->functionName());
            } else {
                uopz_unset_return($mock->functionName());
            }
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
        $key = self::keyForMock($mock);
        return isset(self::$m_mocks[$key]) && in_array($mock, self::$m_mocks[$key]);
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
        $key = self::keyForMock($mock);
        // NOTE the register is guaranteed to be a non-empty array if it is set
        return isset(self::$m_mocks[$key]) && end(self::$m_mocks[$key]) === $mock;
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
        if (!self::mockIsTop($mock)) {
            return false;
        }

        // NOTE there's a bug in uopz where the fn name is lower-cased when stored in the hash table, but the name is
        //  not lower-cased when doing the lookup in uopz_get_return, so we work around that here
        $functionName = mb_convert_case($mock->functionName(), MB_CASE_LOWER);

        if ($mock->isMethod()) {
            return null !== uopz_get_return($mock->className(), $functionName);
        }

        return null !== uopz_get_return(strtolower($functionName));
    }

    /**
     * Suspend mocking of a named function/method.
     *
     * @param string $functionOrClass The function or class for which to suspend mocking.
     * @param ?string $methodName The method for which to suspend mocking if `$functionOrClass` is a class name.
     */
    public static function suspendMock(string $functionOrClass, ?string $methodName = null): void
    {
        if (isset($methodName)) {
            $methodName = mb_convert_case($methodName, MB_CASE_LOWER);

            if (null !== uopz_get_return($functionOrClass, $methodName)) {
                uopz_unset_return($functionOrClass, $methodName);
            }
        } else {
            $functionOrClass = mb_convert_case($functionOrClass, MB_CASE_LOWER);

            if (null !== uopz_get_return($functionOrClass)) {
                uopz_unset_return($functionOrClass);
            }
        }
    }

    public static function suspendAllMocks(): void
    {
        foreach (self::$m_mocks as $mocks) {
            $mock = end($mocks);

            if ($mock->isMethod()) {
                self::suspendMock($mock->className(), $mock->functionName());
            } else {
                self::suspendMock($mock->functionName());
            }
        }
    }

    public static function removeAllMocks(): void
    {
        self::suspendAllMocks();
        self::$m_mocks = [];
    }

    /**
     * Resume mocking of a named function/method.
     *
     * If the named function has no installed mocks an exception is thrown. Otherwise, the mock on top of the function's
     * stack is activated.
     *
     * @param string $functionOrClass The function for which to resume mocking, or the class if it's a method mock.
     * @param ?string $methodName The method for which to resume mocking, or `null` if it's a standalone function mock..
     */
    public static function resumeMock(string $functionOrClass, ?string $methodName = null): void
    {
        $key = self::keyForClassAndFunction($functionOrClass, $methodName);

        if (!isset(self::$m_mocks[$key])) {
            throw new RuntimeException("No mocks are installed for '{$key}'.");
        }

        self::activateMock(end(self::$m_mocks[$key]));
    }

    /**
     * Fetch the active mock for a function/method.
     *
     * @param string $functionOrClass The function name or class name.
     * @param string|null $methodName The method name if the mock sought is for a method.
     *
     * @return MockFunction|null The active mock, or `null` if no mock is active.
     */
    public static function activeMock(string $functionOrClass, ?string $methodName = null): ?MockFunction
    {
        $mock = self::topMock($functionOrClass, $methodName);
        return (isset($mock) && $mock->isActive()) ? $mock : null;
    }

    public static function topMock(string $functionOrClass, ?string $methodName = null): ?MockFunction
    {
        $key = self::keyForClassAndFunction($functionOrClass, $methodName);

        if (!isset(self::$m_mocks[$key])) {
            return null;
        }

        return end(self::$m_mocks[$key]);
    }
}
