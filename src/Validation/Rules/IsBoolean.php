<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\TypeConvertingRule;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that some data is a valid boolean.
 */
class IsBoolean implements TypeConvertingRule
{
    /**
     * Check some data against the rule.
     *
     * Boolean values pass, as do 1, "1", "yes", "true", "on" (true) and 0, "0", "no", "false", "off" (false). Anything
     * else fails.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data a `bool` or a valid bool string, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        // NOTE explicitly reject objects because filter_var() doesn't (can't) reflect strict_types setting and will
        // call __toString() to convert to string and thereby accept objects with a __toString() that returns one of the
        // accepted string values
        return isset($data) && "" !== $data && !is_object($data) && !is_null(filter_var($data, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]));
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
        return tr("The %1 field must be a boolean value.", __FILE__, __LINE__, $field);
    }

    /**
     * Convert some pre-checked data to a bool.
     *
     * @param mixed $data The data that has passed the rule.
     *
     * @return bool The converted bool value.
     */
    public function convert($data): bool
    {
        return filter_var($data, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]);
    }
}
