<?php

declare(strict_types=1);

namespace Bead\Encryption\OpenSsl;

trait ScrubsStrings
{
    private static function scrubString(string &$str): void
    {
        for ($idx = strlen($str) - 1; $idx >= 0; --$idx) {
            $str[$idx] = "\0";
        }
    }
}
