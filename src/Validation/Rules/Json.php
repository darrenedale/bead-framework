<?php

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;
use Throwable;

/**
 * Validator rule to ensure that some data is a valid string representation of some JSON.
 */
class Json implements Rule
{
    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is a valid string representation of some JSON, `false` otherwise..
     */
    public function passes(string $field, $data): bool
    {
        try {
            return is_string($data) && json_decode($data, false, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $err) {
            return false;
        }
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
        return tr("The %1 field must be valid JSON.", __FILE__, __LINE__, $field);
    }
}
