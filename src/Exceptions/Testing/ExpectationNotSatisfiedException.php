<?php

namespace Bead\Exceptions\Testing;

use RuntimeException;

/** Thrown when one or more MockFunction expectations has not been satisfied. */
class ExpectationNotSatisfiedException extends RuntimeException
{
}
