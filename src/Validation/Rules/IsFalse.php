<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class IsFalse implements Rule
{
    public function passes(string $field, $data): bool
    {
        return false === filter_var($data, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be false.", __FILE__, __LINE__, $field);
    }
}
