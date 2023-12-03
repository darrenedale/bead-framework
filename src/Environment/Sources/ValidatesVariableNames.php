<?php

declare(strict_types=1);

namespace Bead\Environment\Sources;

use function preg_match;
use function trim;

trait ValidatesVariableNames
{
    /**
     * Ensure a name is valid as an environment variable name.
     *
     * Valid names start with an English letter or an underscore and contain only English letters, Arabic digits and
     * underscore characters. Technically, an environment variable name *could* be anything in the OpenGroup's portable
     * character set (https://pubs.opengroup.org/onlinepubs/9699919799/basedefs/V1_chap06.html) but most shell utilities
     * limit support to only the subset validated here.
     *
     * @param string $name The name to validate.
     *
     * @return string|null The validated name or null if the name is invalid.
     */
    private static function validateVariableName(string $name): ?string
    {
        if (preg_match("/^\s*[_a-zA-Z][_a-zA-Z0-9]*\s*\$/", $name)) {
            return trim($name);
        }

        return null;
    }
}
