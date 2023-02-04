<?php

namespace Bead\Testing;

// NOTE use of ReflectionIntersectionType is guarded by PHP version check
use Error;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use TypeError;


/**
 * Class to check whether some arguments are valid for a function.
 */
class FunctionArgumentChecker
{
    private ?string $m_class;
    private string $m_function;

    /** @var array<ReflectionParameter> - lazily populated when first required. */
    private ?array $m_parameters;

    /**
     * Initialise a new argument checker for a function/method.
     *
     * @param string $functionOrClassName The name of the function to check against, or the class if it's a method.
     * @param string|null $methodName The name of the method to check against.
     */
    public function __construct(string $functionOrClassName, ?string $methodName = null)
    {
        $this->m_parameters = null;

        if (isset($methodName)) {
            $this->m_class = $functionOrClassName;
            $this->m_function = $methodName;
        } else {
            $this->m_class = null;
            $this->m_function = $functionOrClassName;
        }
    }

    public function className(): ?string
    {
        return $this->m_class;
    }

    /**
     * Fetch the name of the function.
     *
     * @return string The function name.
     */
    public function functionName(): string
    {
        return $this->m_function;
    }

    public function isMethod(): bool
    {
        return isset($this->m_class);
    }

    /**
     * Fetch the parameters for the function.
     *
     * @return ReflectionParameter[]
     */
    protected function parameters(): array
    {
        if (!isset($this->m_parameters)) {
            $this->readParameters();
        }

        return $this->m_parameters;
    }

    protected final function checkNamedType(ReflectionNamedType $type, $arg): void
    {
        $paramType = $type->getName();

        if (is_object($arg)) {
            if (!is_a($arg, $paramType, true)) {
                $argClass = get_class($arg);
                throw new Error("expected {$paramType}, {$argClass} found.");
            }
        } else {
            $argType = gettype($arg);

            // integers and floats can be automatically cast to one another, even under strict_types
            // NOTE that reflection API provides "int" as the type name for ints...
            if ($paramType === "int" || $paramType === "float") {
                $paramType = "number";
            }

            // ... while gettype provides "integer" for int values
            if ($argType === "integer" || $argType === "float") {
                $argType = "number";
            }

            if ($paramType !== $argType) {
                throw new TypeError("expected {$paramType}, {$argType} found.");
            }
        }
    }

    /**
     * @param ReflectionUnionType $type
     * @param mixed $value
     */
    protected final function checkUnionType($type, $value): void
    {
        foreach ($type->getTypes() as $memberType) {
            try {
                $this->checkNamedType($memberType, $value);
                return;
            } catch (Error $err) {
                // nothing to do, try the next type
            }
        }

        throw new Error(
            "expected " .
            grammaticalImplode(
                array_map(fn(ReflectionNamedType $type) => $type->getName(), $type->getTypes()),
                ", ",
                " or "
            ) .
            "; found " . (is_object($value) ? get_class($value) : gettype($value))
        );
    }

    /**
     * @param ReflectionIntersectionType $type
     * @param mixed $value
     */
    protected final function checkIntersectionType($type, $value): void
    {
        foreach ($type->getTypes() as $memberType) {
            try {
                $this->checkNamedType($memberType, $value);
            } catch (Error $err) {
                throw new Error(
                    "expected " .
                    grammaticalImplode(
                        array_map(fn(ReflectionNamedType $type) => $type->getName(), $type->getTypes()),
                    ) .
                    "; found " . (is_object($value) ? get_class($value) : gettype($value))
                );
            }
        }
    }

    /**
     * Check some args for the function.
     *
     * The arguments to check are passed by reference so that they can be modified with the default values for any
     * optional arguments that are not provided.
     *
     * A TypeError is thrown if the arguments are not valid.
     *
     * @param ...$args array The arguments to check.
     */
    public function check(...$args): void
    {
        $params = $this->parameters();

        for ($idx = 0; $idx < count($params); ++$idx) {
            $param = $params[$idx];

            if ($idx >= count($args)) {
                if (!$param->isOptional()) {
                    throw new TypeError("Argument #{$param->getPosition()} ({$param->getName()}) of {$param->getDeclaringFunction()->getName()}() is not optional, has no default value and no value was passed.");
                }

                break;
            }

            if (!$param->hasType()) {
                // any arg is valid for an untyped parameter
                continue;
            }

            $arg = $args[$idx];
            /** @var ReflectionNamedType $paramType */
            $paramType = $param->getType();

            if ($paramType->allowsNull() && null === $arg) {
                continue;
            }

            try {
                if (8 <= PHP_MAJOR_VERSION) {
                    if ($paramType instanceof ReflectionUnionType) {
                        $this->checkUnionType($paramType, $arg);
                        continue;
                    } else if (80100 <= PHP_VERSION_ID && $paramType instanceof ReflectionIntersectionType) {
                        $this->checkIntersectionType($paramType, $arg);
                        continue;
                    }
                }

                $this->checkNamedType($paramType, $arg);
            } catch (Error $err) {
                throw new TypeError("Argument #{$param->getPosition()} ({$param->getName()}) of {$param->getDeclaringFunction()->getName()}() - {$err->getMessage()}");
            }
        }
    }

    /**
     * Read the params the function requires.
     */
    protected final function readParameters(): void
    {
        if ($this->isMethod()) {
            $this->m_parameters = (new ReflectionMethod($this->className(), $this->functionName()))->getParameters();
        } else {
            $this->m_parameters = (new ReflectionFunction($this->functionName()))->getParameters();
        }
    }
}
