<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use DateTime;
use Bead\Exceptions\ValidationRuleException;

use function Bead\Helpers\I18n\tr;

/**
 * Validation rule that ensures a value is greater than the value in another field in the dataset.
 *
 * If the other field is not present in the dataset, or can't be coerced into an appropriate value for comparison with
 * the provided data, a ValidationRuleException is thrown.
 *
 * The rule works with int, float, DateTime, array and string data in the following ways:
 *
 * - if the data is an int, or can be coerced to an int, it must be greater than the int value in the referenced field.
 * - if the data is aa float, or can be coerced to a float, it must be greater than the float value in the referenced
 *   field.
 * - if the data is a DateTime, or can be converted to a DateTime by the DateTime constructor, it must be before the
 *   DateTime value in the referenced field.
 * - if the data is an array, it must contain fewer elements than the int value in the referenced field.
 * - if the data is a string, it must contain fewer characters than the int value in the referenced field.
 */
class GreaterThan extends FieldComparingRule
{
    /**
     * Helper to check an int using the rule.
     *
     * @param int $data The int to check.
     *
     * @return bool `true` if the int is no bigger than the value in the referenced field, `false` otherwise.
     * @throws ValidationRuleException if the referenced field does not contain an integer.
     */
    protected function intPasses(int $data): bool
    {
        return $data > $this->otherValueAsFloat();
    }

    /**
     * Helper to check a float using the rule.
     *
     * @param float $data The float to check.
     *
     * @return bool `true` if the float is no bigger than the value in the referenced field, `false` otherwise.
     * @throws ValidationRuleException if the referenced field does not contain a float.
     */
    protected function floatPasses(float $data): bool
    {
        return $data > $this->otherValueAsFloat();
    }

    /**
     * Helper to check a string using the rule.
     *
     * @param string $data The string to check.
     *
     * @return bool `true` if the string is no longer than the number of characters specified in the referenced field,
     * `false` otherwise.
     * @throws ValidationRuleException if the referenced field does not contain an integer.
     */
    protected function stringPasses(string $data): bool
    {
        return mb_strlen($data, "UTF-8") > $this->otherValueAsInt();
    }

    /**
     * Helper to check an array using the rule.
     *
     * @param array $data The array to check.
     *
     * @return bool `true` if the array has no more than the number of elements specified in the referenced field,
     * `false` otherwise.
     * @throws ValidationRuleException if the referenced field does not contain an integer.
     */
    protected function arrayPasses(array $data): bool
    {
        return count($data) > $this->otherValueAsInt();
    }

    /**
     * Helper to check a DateTime using the rule.
     *
     * @param DateTime $data The DateTime to check.
     *
     * @return bool `true` if the DateTime is before the DateTime in the referenced field.
     * @throws ValidationRuleException if the referenced field does not contain a DateTime.
     */
    protected function dateTimePasses(DateTime $data): bool
    {
        return $data > $this->otherValueAsDateTime();
    }

    /**
     * @inheritDoc
     */
    public function message(string $field): string
    {
        return tr("The %1 field must be greater than the value in the %2 field.", __FILE__, __LINE__, $field, $this->otherField());
    }
}
