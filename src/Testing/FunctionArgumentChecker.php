<?php

namespace Equit\Testing;

use ReflectionFunction;
use ReflectionParameter;
use TypeError;

/**
 * Class to check whether some arguments are valid for a function.
 */
class FunctionArgumentChecker
{
    private string $m_function;

    /** @var array<ReflectionParameter> - lazily populated when first required. */
    private ?array $m_parameters;

    /**
     * Initialise a new argument checker for a function.
     *
     * @param string $functionName The name of the function to check against.
     */
    public function __construct(string $functionName)
    {
        $this->m_parameters = null;
        $this->m_function = $functionName;
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
            $paramType = $param->getType();

            if ($paramType->allowsNull() && null === $arg) {
                continue;
            }

            $paramType = $paramType->getName();

            if (is_object($arg)) {
                if (!is_a($arg, $paramType, true)) {
                    $argClass = get_class($arg);
                    throw new TypeError("Argument #{$param->getPosition()} ({$param->getName()}) of {$param->getDeclaringFunction()->getName()}() expects an instance of {$paramType}, {$argClass} found.");
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
                    throw new TypeError("Argument #{$param->getPosition()} ({$param->getName()}) of {$param->getDeclaringFunction()->getName()}() expects a {$paramType}, {$argType} found.");
                }
            }
        }
    }

    /**
     * Read the params the function requires.
     */
    protected final function readParameters(): void
    {
        $this->m_parameters = (new ReflectionFunction($this->functionName()))->getParameters();
    }
}
