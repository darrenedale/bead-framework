<?php

namespace BeadTests\Framework\Constraints\Email;

use Bead\Contracts\Email\Header as HeaderContract;
use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Part as PartContract;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Constraint requiring a Message or Part to contain a specific Header instance.
 *
 * The Message or Part must contain the identical Header instance. If what you want is a check to see whether a Message
 * or Part contains a Header that is equal to a given Header, use HasEquivalentHeader.
 */
class HasHeader extends Constraint
{
    /** @var mixed The header to match. */
    private HeaderContract $header;

    /**
     * Initialise an instance of the constraint with a given Header.
     *
     * @param HeaderContract $header The header that must be contained.
     */
    public function __construct(HeaderContract $header)
    {
        $this->header = $header;
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
        if (!($other instanceof MessageContract) && !($other instanceof PartContract)) {
            return false;
        }

        foreach ($other->headers() as $header) {
            if ($header === $this->header) {
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
        return "has header '{$this->header->line()}'";
    }
}
