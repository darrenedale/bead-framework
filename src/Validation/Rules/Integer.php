<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\TypeConvertingRule;

/**
 * Validator rule to ensure that some data is a valid integer.
 *
 * `int` values pass, as do strings that contain valid decimal integers and no other content. The only content other
 * than numeric digits that is accepted is an optional leading '-' sign. Leading 0s are not accepted (i.e. "123" is
 * valid whereas "0123" is not.
 */
class Integer implements TypeConvertingRule
{
    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data an int or a valid int string, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        // NOTE FILTER_VALIDATE_INT accepts bool `true` as valid int
        return true !== $data && false !== filter_var($data, FILTER_VALIDATE_INT);
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
        return tr("The %1 field must be an integer.", __FILE__, __LINE__, $field);
    }

    /**
     * Convert some pre-checked data to an int.
     *
     * @param mixed $data The data that has passed the rule.
     *
     * @return int The converted int value.
     */
    public function convert($data): int
    {
        return filter_var($data, FILTER_VALIDATE_INT);
    }
}