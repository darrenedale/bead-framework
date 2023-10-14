<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use DateTime;
use Bead\Validation\TypeConvertingRule;
use Exception;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that some data is a date.
 *
 * If the data is a string, an attempt will be made to interpret it as a DateTime. If this succeeds, or the data is a
 * DateTime already, the rule passes; otherwise it fails. `strtotime()` is used for the conversion.
 */
class Date implements TypeConvertingRule
{
    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is a DateTime or a valid DateTime string, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return $data instanceof DateTime || false !== strtotime($data);
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
        return tr("The %1 field must be a valid date.", __FILE__, __LINE__, $field);
    }

    /**
     * Fetch the data as a DateTime object.
     *
     * If converting from a string, the returned DateTime will be in UTC.
     *
     * @param mixed $data The data that has passed validation.
     *
     * @return DateTime The DateTime.
     * @throws Exception if the provided data is not a valid DateTime string.
     */
    public function convert($data): DateTime
    {
        return ($data instanceof DateTime ? $data : new DateTime("@" . strtotime($data), new \DateTimeZone("UTC")));
    }
}
