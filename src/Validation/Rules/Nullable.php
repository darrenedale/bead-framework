<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\TypeConvertingRule;
use Bead\Validation\ValidatorAwareRule;

/**
 * Validator rule to make a field nullable.
 *
 * This rule is similar to optional, except that it ensures the validated value is `null` if it's empty whereas optional
 * will leave it as, for example, an empty string.
 *
 * This rule always passes; if the data is empty the errors for the field are cleared and the remaining rules for the
 * field are skipped - if the field is nullable and is empty, all other rules are irrelevant.
 */
class Nullable implements ValidatorAwareRule, TypeConvertingRule
{
    use KnowsValidator;
    use ChecksDataForEmptiness;

    /**
     * Checks the value for nullability.
     *
     * This rule always passes; if the data is empty the errors for the field are cleared and the remaining rules for
     * the field are skipped - if the field is nullable and is empty, all other rules are irrelevant.
     *
     * @param string $field The field whose data is being tested.
     * @param mixed $data The data to test.
     *
     * @return bool true
     */
    public function passes(string $field, $data): bool
    {
        if (self::isEmpty($data) && $this->validator()) {
            $this->validator()->clearErrors($field);
            $this->validator()->skipRemainingRules($field);
        }

        return true;
    }

    /**
     * If the data is empty, convert it to null.
     *
     * @param mixed $data The data to convert.
     *
     * @return mixed|null
     */
    public function convert($data)
    {
        if (self::isEmpty($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Fetch the default message for when the data does not pass the rule.
     *
     * This rule never fails, so it just returns an empty string.
     *
     * @param string $field The field under validation.
     *
     * @return string An empty string.
     */
    public function message(string $field): string
    {
        return "";
    }
}
