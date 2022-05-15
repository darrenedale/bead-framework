<?php

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\KnowsValidator;
use Equit\Validation\ValidatorAwareRule;

/**
 * Validator rule to make a field optional.
 *
 * This rule always passes; if the data is empty the errors for the field are cleared and the remaining rules for
 * the field are skipped - if the field is optional and is empty, all other rules are irrelevant.
 */
class Optional implements ValidatorAwareRule
{
    use KnowsValidator;
    use ChecksDataForEmptiness;

    /**
     * Checks the value for emptiness.
     *
     * This rule always passes; if the data is empty the errors for the field are cleared and the remaining rules for
     * the field are skipped - if the field is optional and is empty, all other rules are irrelevant.
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
