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
 * Validator rule to ensure that a string is a valid IP address.
 */
class Ip implements Rule
{
    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is valid IP address, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return false !== filter_var($data, FILTER_VALIDATE_IP);
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
        return tr("The %1 field must be a valid IP address.", __FILE__, __LINE__, $field);
    }
}