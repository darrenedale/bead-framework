<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\TypeConvertingRule;
use LogicException;

/**
 * Validator rule to ensure that some data is a valid boolean `false`.
 */
class IsFalse implements TypeConvertingRule
{
    /**
     * Check some data against the rule.
     *
     * Boolean `false` passes, as do 0, "0", "no", "false", "off". Anything else fails.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is `false` or a valid false string, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        // NOTE explicitly reject objects because filter_var() doesn't (can't) reflect strict_types setting and will
        // call __toString() to convert to string and thereby accept objects with a __toString() that returns one of the
        // accepted string values
        return isset($data) && "" !== $data && !is_object($data) && false === filter_var($data, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]);
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
        return tr("The %1 field must be false.", __FILE__, __LINE__, $field);
    }

    /**
     * Convert some pre-checked data to a bool.
     *
     * @param mixed $data The data that has passed the rule.
     *
     * @return false The converted value.
     */
    public function convert($data): bool
    {
        assert(false === filter_var($data, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]), (
        8 <= PHP_MAJOR_VERSION
            ? new LogicException("IsFalse::convert() called with a value that does not pass the rule.")
            : "IsFalse::convert() called with a value that does not pass the rule."
        ));
        return false;
    }
}
