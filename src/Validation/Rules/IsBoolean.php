<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class IsBoolean implements Rule
{
    public function passes(string $field, $data): bool
    {
        return !is_null(filter_var($data, FILTER_VALIDATE_BOOLEAN, ["flags" => FILTER_NULL_ON_FAILURE,]));
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be a boolean value.", __FILE__, __LINE__, $field);
    }
}
