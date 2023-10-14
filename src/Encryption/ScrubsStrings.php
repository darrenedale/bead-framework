<?php

declare(strict_types=1);

namespace Bead\Encryption;

/**
 * Shared implementation of method to securely erase string content.
 */
trait ScrubsStrings
{
    /** Overwrite a string's content with random bytes. */
    private static function scrubString(string &$str): void
    {
        for ($idx = strlen($str) - 1; $idx >= 0; --$idx) {
            $str[$idx] = chr(rand(0, 255));
        }
    }
}
