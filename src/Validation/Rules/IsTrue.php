<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\TypeConvertingRule;
use LogicException;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that some data is a valid boolean `true`.
 */
class IsTrue implements TypeConvertingRule
{
    /**
     * Check some data against the rule.
     *
     * Boolean `true` passes, as do 1, "1", "yes", "true", "on". Anything else fails.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is `true` or a valid true string, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        // NOTE explicitly reject objects because filter_var() doesn't (can't) reflect strict_types setting and will
        // call __toString() to convert to string and thereby accept objects with a __toString() that returns one of the
        // accepted string values
        return !is_object($data) && true === filter_var($data, FILTER_VALIDATE_BOOLEAN);
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
        return tr("The %1 field must be true.", __FILE__, __LINE__, $field);
    }

    /**
     * Convert some pre-checked data to a bool.
     *
     * @param mixed $data The data that has passed the rule.
     *
     * @return true The converted value.
     */
    public function convert($data): bool
    {
        assert(true === filter_var($data, FILTER_VALIDATE_BOOLEAN), (
            8 <= PHP_MAJOR_VERSION
            ? new LogicException("IsTrue::convert() called with a value that does not pass the rule.")
            : "IsTrue::convert() called with a value that does not pass the rule."
        ));
        return true;
    }
}
