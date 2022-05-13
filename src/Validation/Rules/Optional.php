<?php

namespace Equit\Validation\Rules;

use Equit\Validation\KnowsValidator;
use Equit\Validation\ValidatorAwareRule;

class Optional implements ValidatorAwareRule
{
    use KnowsValidator;

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
        if (!is_int($data) && !is_float($data) && empty($data) && $this->validator()) {
            $this->validator()->clearErrors($field);
            $this->validator()->skipRemainingRules($field);
        }

        return true;
    }

    /**
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