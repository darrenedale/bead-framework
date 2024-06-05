<?php

namespace BeadTests\Framework\Constraints\Email;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Part as PartContract;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Constraint requiring a Message to contain a specific Part instance.
 *
 * The Message must contain the identical Part instance.
 */
class HasPart extends Constraint
{
    /** @var mixed The headaer to match. */
    private PartContract $part;

    /**
     * Initialise an instance of the constraint with a given Part.
     *
     * @param PartContract $part The part that must be contained.
     */
    public function __construct(PartContract $part)
    {
        $this->part = $part;
    }

    /**
     * Check whether a message or message part has the header.
     *
     * @param mixed $other The Message or Part to test against the constraint.
     *
     * @return bool `true` if the value satisfies the constraint, `false` if not.
     */
    public function matches(mixed $other): bool
    {
        return $other instanceof MessageContract && in_array($this->part, $other->parts(), true);
    }

    /**
     * Fetch a description of the constraint.
     */
    public function toString(): string
    {
        return "has part " . $this->exporter()->export($this->part);
    }
}
