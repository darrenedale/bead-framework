<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

/**
 * Validator rule to ensure that some data is not empty.
 *
 * Empty means `empty()`, except that `0` (float or int) and `false` are not considered empty.
 */
class Filled implements Rule
{
    use ChecksDataForEmptiness;

    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is non-empty, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return self::isFilled($data);
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
        return tr("The %1 field must not be empty.", __FILE__, __LINE__, $field);
    }
}
