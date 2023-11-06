<?php

declare(strict_types=1);

namespace Bead\Testing;

use Bead\Facades\Log;
use Closure;
use LogicException;
use ReflectionFunction;
use ReflectionUnionType;

/**
 * Class to match arguments provided to mocked functions in Expectations.
 *
 * Use instances of this class when you want your Expectations to match arguments used to call mocked fucntions based on
 * logic more complex that simple equality comparisons.
 */
class MockArgumentMatcher
{
    /**
     * @var callable $match The callable that determines whether a value is a match or not.
     *
     * In the currenlty targeted PHP version, closure can't be used as a type declaration, so we use mixed.
     */
    private mixed $match;

    private function __construct(callable $match)
    {
        echo "Initialising matcher\n";
        $this->match = $match;
    }

    /**
     * Determine whether an argument matches the matcher.
     *
     * @param mixed $value The argument value to check.
     *
     * @return bool `true` if the value is a match, `false` if not.
     */
    public function valueMatches(mixed $value): bool
    {
        return ($this->match)($value);
    }

    /** Matcher that matches any argument. */
    final public static function any(): self
    {
        return new self(fn(mixed $arg): bool => true);
    }

    /** Matcher that never matches an argument. */
    final public static function none(): self
    {
        return new self(fn(mixed $arg): bool => false);
    }

    /**
     * Matcher that checks whether an argument is of a given type
     *
     * @param type-string|class-string $type
     */
    final public static function type(string $type): self
    {
        return match ($type) {
            // if it's a scalar type, check against gettype()
            "boolean", "integer", "double", "string", "array", "object",
            "resource", "resource (closed)", "NULL", "unknown type" => new self(fn(mixed $arg): bool => $type === gettype($arg)),

            // otherwise, assume $type is a FQ class name
            default => new self(fn(mixed $arg): bool => $arg instanceof $type),
        };
    }

    /** Matcher that checks whether an argument is not null. */
    final public static function notNull(): self
    {
        return new self(fn(mixed $arg): bool => null !== $arg);
    }

    /**
     * Matcher that uses a closure to determine whether an argument matches.
     *
     * The closure must take a single parameter either without a type declaration or declared as `mixed` and have a
     * return type declaration of `bool`.
     */
    final public static function matches(Closure $fn): self
    {
        $reflector = new ReflectionFunction($fn);
        $params = $reflector->getParameters();
        assert (1 === count($params), new LogicException("Closures for MockArgumentMatcher instances must take exactly one parameter."));
        assert (!$params[0]->hasType() || "mixed" === $params[0]->getType()->getName(), "The parameter for a Closure for a MockArgumentMatcher instance must be untyped or mixed.");
        assert ($reflector->hasReturnType() , new LogicException("Closures for MockArgumentMatcher instances must have a bool return type."));
        $returnType = $reflector->getReturnType();
        assert (!$returnType instanceof ReflectionUnionType, new LogicException("Closures for MockArgumentMatcher instances must have a bool return type."));
        assert ("bool" === $returnType->getName(), new LogicException("Closures for MockArgumentMatcher instances must have a bool return type."));

        return new self($fn);
    }
}
