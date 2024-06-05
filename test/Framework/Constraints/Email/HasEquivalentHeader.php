<?php

namespace BeadTests\Framework\Constraints\Email;

use Bead\Contracts\Email\Header as HeaderContract;
use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Part as PartContract;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\IsEqualCanonicalizing;

/**
 * Constraint requiring a Message or Part to contain a Header matching a given Header.
 *
 * Equivalent headers have:
 * - the same name (not case-sensitive)
 * - the same value (case-sensitive)
 * - the same set of parameters with the same values (in any order)
 */
class HasEquivalentHeader extends Constraint
{
    /** @var mixed The header to match. */
    private HeaderContract $header;

    private IsEqualCanonicalizing $parametersConstraint;

    /**
     * Initialise an instance of the constraint with a given Header.
     *
     * @param HeaderContract $header The header that must be contained.
     */
    public function __construct(HeaderContract $header)
    {
        $this->header = $header;
    }

    private function headerMatches(HeaderContract $header): bool
    {
        if (strtolower($header->name()) !== strtolower($this->header->name())) {
            return false;
        }

        if ($header->value() !== $this->header->value()) {
            return false;
        }

        if (!isset($this->parametersConstraint)) {
            $this->parametersConstraint = new IsEqualCanonicalizing($this->header->parameters());
        }

        return $this->parametersConstraint->evaluate($header->parameters(), "", true);
    }

    /**
     * Check whether a message or message part has an equivalent header.
     *
     * @param mixed $other The Message or Part to test against the constraint.
     *
     * @return bool `true` if the value satisfies the constraint, `false` if not.
     */
    public function matches(mixed $other): bool
    {
        if (!($other instanceof MessageContract) && !($other instanceof PartContract)) {
            return false;
        }

        foreach ($other->headers() as $header) {
            if ($this->headerMatches($header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch a description of the constraint.
     */
    public function toString(): string
    {
        return "has header equivalent to '{$this->header->line()}'";
    }
}
