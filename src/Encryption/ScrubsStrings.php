<?php

declare(strict_types=1);

namespace Bead\Encryption;

use Bead\Helpers\Str;

/**
 * Shared implementation of method to securely erase string content.
 */
trait ScrubsStrings
{
    /** Overwrite a string's content with random bytes. */
    final protected static function scrubString(string & $str): void
    {
        Str\scrub($str);
    }
}
