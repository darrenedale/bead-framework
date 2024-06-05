<?php

declare(strict_types=1);

namespace Bead\Exceptions;

use RuntimeException;

/** Exception thrown by environment handling classes when a source, name or value isn't valid. */
class EnvironmentException extends RuntimeException
{
}
