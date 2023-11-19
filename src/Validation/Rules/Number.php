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
 * Validator rule to ensure that some data is a valid number.
 */
class Number implements TypeConvertingRule
{
    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data a number or a valid number string, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        // NOTE FILTER_VALIDATE_FLOAT accepts bool `true` as valid float
        return true !== $data && false !== filter_var($data, FILTER_VALIDATE_FLOAT);
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
        return tr("The %1 field must be an number.", __FILE__, __LINE__, $field);
    }

    /**
     * Convert some pre-checked data to an float.
     *
     * @param mixed $data The data that has passed the rule.
     *
     * @return float The converted float value.
     */
    public function convert($data): float
    {
        return filter_var($data, FILTER_VALIDATE_FLOAT);
    }
}
