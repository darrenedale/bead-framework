<?php

namespace Bead\Testing;

use InvalidArgumentException;
use Throwable;

class Expectation
{
    /** @var int Outcome type that returns a fixed value when the Expectation matches. */
    public const OutcomeValue = 0;

    /** @var int Outcome type that calls a callable and returns its return value when the Expectation matches. */
    public const OutcomeCallableResult = 1;

    /** @var int Outcome type that throws an exception when the Expectation matches.*/
    public const OutcomeThrow = 2;

    /** @var int What type of outcome is produced if this Expectation matches. */
    private int $outcomeType = self::OutcomeValue;

    /** @var mixed|null The outcome of calling the mocked function if this Expectation matches. */
    private mixed $outcome = null;

    /** @var array|null The arguments (or argument matchers) that are expected (or null if any arguments match). */
    private ?array $expectedArguments = null;

    /** @var int >= 0 if there is an expectation of a precise number of matching calls. */
    private int $expectedTimes = -1;

    /**@var int > 0 if the expectation is that at least a number of matching calls will be made. */
    private int $minimumTimes = 1;

    /** @var int How many matching calls have actually been made. */
    private int $actualTimes = 0;

    /** @var bool true if the expectation is a default that can be used when specific matches are not found. */
    private bool $isDefault = false;

    public function __constructor(mixed ... $expectedArguments)
    {
        $this->expectedArguments = $expectedArguments;
    }

    public static function forAnyArguments(): self
    {
        return new self();
    }

    public static function forArguments(mixed ... $args): self
    {
        $expectation = new self();
        $expectation->expectedArguments = $args;
        return $expectation;
    }

    public function once(): self
    {
        $this->expectedTimes = 1;
        return $this;
    }

    public function times(int $times): self
    {
        if (1 > $times) {
            throw new InvalidArgumentException("Expected call count > 0, found {$times}");
        }

        $this->expectedTimes = $times;
        return $this;
    }

    public function zeroOrMoreTimes(): self
    {
        $this->expectedTimes = -1;
        $this->minimumTimes = 0;
        return $this;
    }

    public function andReturn(mixed $value): self
    {
        $this->outcomeType = self::OutcomeValue;
        $this->outcome = $value;
        return $this;
    }

    public function andReturnUsing(callable $fn): self
    {
        $this->outcomeType = self::OutcomeCallableResult;
        $this->outcome = $fn;
        return $this;
    }

    public function andThrow(Throwable $error): self
    {
        $this->outcomeType = self::OutcomeThrow;
        $this->outcome = $error;
        return $this;
    }

    public function byDefault(): self
    {
        $this->isDefault = true;
        $this->expectedTimes = -1;
        return $this;
    }

    public function matches(mixed ... $actualArguments): bool
    {
        if (null === $this->expectedArguments) {
            return true;
        }

        if (count($actualArguments) !== count($this->expectedArguments)) {
            return false;
        }

        for ($idx = 0; $idx < count($this->expectedArguments); ++$idx) {
            $expected = $this->expectedArguments[$idx];
            $actual = $actualArguments[$idx];

            if ($expected instanceof MockArgumentMatcher) {
                if (!$expected->valueMatches($actual)) {
                    return false;
                }
            } elseif ($expected !== $actual) {
                return false;
            }
        }

        return true;
    }

    public function hasExpired(): bool
    {
        if ($this->isDefault) {
            return false;
        }

        if (0 <= $this->expectedTimes && $this->actualTimes >= $this->expectedTimes) {
            return true;
        }

        return false;
    }

    public function exec(mixed ... $actualArguments): mixed
    {
        ++$this->actualTimes;

        switch ($this->outcomeType) {
            case self::OutcomeValue:
                return $this->outcome;

            case self::OutcomeCallableResult:
                return ($this->outcome)(...$actualArguments);

            case self::OutcomeThrow:
                throw $this->outcome;
        }
    }

    public function isSatisfied(): bool
    {
        if ($this->isDefault) {
            return true;
        }

        if (0 <= $this->expectedTimes && $this->actualTimes !== $this->expectedTimes) {
            return false;
        }

        if (0 <= $this->minimumTimes && $this->actualTimes < $this->minimumTimes) {
            return false;
        }

        return true;
    }

    private function returnValueDescription(): string
    {
        return match ($this->outcomeType) {
            self::OutcomeValue => "return a set value",
            self::OutcomeCallableResult => "return the result of a callback",
            self::OutcomeThrow => "throw an exception of type " . get_class($this->outcome),
            default => ""
        };
    }

    // TODO add argument descriptions
    public function description(): string
    {
        if ($this->isDefault) {
            return "will {$this->returnValueDescription()} by default";
        }

        if (0 <= $this->expectedTimes) {
            return "will be called exactly {$this->expectedTimes} time(s) and {$this->returnValueDescription()}";
        }

        if (0 <= $this->minimumTimes) {
            return "will be called at least {$this->minimumTimes} time(s) and {$this->returnValueDescription()}";
        }

        return "will {$this->returnValueDescription()}";
    }
}
