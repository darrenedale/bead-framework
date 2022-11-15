<?php

namespace Equit\Exceptions\Concurrent;

use RuntimeException;

/**
 * Thrown when the PHP extension for shared memory support is not present.
 *
 * Only ever thrown from one of the SharedMemory factory methods.
 */
class SharedMemoryExtensionMissingException extends RuntimeException
{
}
