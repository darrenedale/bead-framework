<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\Rule;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that some data is an array.
 */
class IsArray implements Rule
{
    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is an array, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return is_array($data);
    }

    /**
     * Fetch the default message for when the data does not pass the rule.
     *
     * @param string $field The field under validation.
     *
     * @return string The message.
     */
    public function message(string $field): string
    {
        return tr("The %1 field must be an array.", __FILE__, __LINE__, $field);
    }
}
