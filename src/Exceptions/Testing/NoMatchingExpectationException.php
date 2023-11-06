<?php

namespace Bead\Exceptions\Testing;

use RuntimeException;

/** Thrown when MockFunction receives a call for which there is no matching expectation. */
class NoMatchingExpectationException extends RuntimeException
{
}
