<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

/**
 * Validator rule to ensure that some data is a string.
 */
class IsString implements Rule
{
    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is a string, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return is_string($data);
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
        return tr("The %1 field must be a string.", __FILE__, __LINE__, $field);
    }
}
