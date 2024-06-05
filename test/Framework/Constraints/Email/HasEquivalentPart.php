<?php

namespace BeadTests\Framework\Constraints\Email;

use Bead\Contracts\Email\Header as HeaderContract;
use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Part as PartContract;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\IsEqualCanonicalizing;

/**
 * Constraint requiring a Message to contain a Part matching a given Part.
 *
 * Equivalent headers have:
 * - the same content
 * - matching headers in any order
 */
class HasEquivalentPart extends Constraint
{
    /** @var mixed The part to match. */
    private PartContract $part;

    private IsEqualCanonicalizing $headersConstraint;

    /**
     * Initialise an instance of the constraint with a given Part.
     *
     * @param PartContract $part The part that must be matched.
     */
    public function __construct(PartContract $part)
    {
        $this->part = $part;
    }

    private static function headerToArray(HeaderContract $header): array
    {
        return [
            "name" => $header->name(),
            "value" => $header->value(),
            "parameters" => $header->parameters(),
        ];
    }

    private function partMatches(PartContract $part): bool
    {
        if ($part->body() !== $this->part->body()) {
            return false;
        }

        if (!isset($this->headersConstraint)) {
            $this->headersConstraint = new IsEqualCanonicalizing(array_map([self::class, "headerToArray",], $this->part->headers()));
        }

        return $this->headersConstraint->evaluate(array_map([self::class, "headerToArray",], $part->headers()), "", true);
    }

    /**
     * Check whether a message has an equivalent part.
     *
     * @param mixed $other The Message to test against the constraint.
     *
     * @return bool `true` if the value satisfies the constraint, `false` if not.
     */
    public function matches(mixed $other): bool
    {
        if (!($other instanceof MessageContract)) {
            return false;
        }

        foreach ($other->parts() as $part) {
            if ($this->partMatches($part)) {
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
        return "has part equivalent to " . $this->exporter()->export($this->part);
    }
}
