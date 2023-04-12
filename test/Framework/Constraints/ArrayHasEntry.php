<?php

namespace BeadTests\Framework\Constraints;

use ArrayAccess;
use PHPUnit\Framework\Constraint\Constraint;
use Stringable;

class ArrayHasEntry extends Constraint
{
    /** @var mixed The key of the entry to match. */
    private mixed $key;

    /** @var mixed The value of the entry to match. */
    private mixed $value;

    /**
     * Initialise an instance of the constraint with a given key and value.
     *
     * @param mixed $key The key to match.
     * @param mixed $value The value to match.
     */
    public function __construct(mixed $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Check whether a value satisfies the constraint.
     *
     * @param mixed $other The value to test against the constraint.
     *
     * @return bool `true` if the value satisfies the constraint, `false` if not.
     */
    public function matches(mixed $other): bool
    {
        if (is_array($other)) {
            return array_key_exists($this->key, $other) && $other[$this->key] === $this->value;
        }

        if ($other instanceof ArrayAccess) {
            return $other->offsetExists($this->key) && $other[$this->key] === $this->value;
        }

        return false;
    }

    /**
     * Fetch a description of the constraint.
     */
    public function toString(): string
    {
        $str = "has entry";

        if (is_string($this->key) || is_int($this->key) || is_float($this->key) || $this->key instanceof Stringable) {
            $str .= " {$this->key}";
        }

        if (is_string($this->value) || is_int($this->value) || is_float($this->value) || $this->value instanceof Stringable) {
            $str .= " with value {$this->value}";
        }

        return $str;
    }
}