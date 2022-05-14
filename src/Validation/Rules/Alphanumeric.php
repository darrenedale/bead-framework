<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class Alphanumeric implements Rule
{

    public function passes(string $field, $data): bool
    {
        return is_string($data) && ctype_alnum($data);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must a string with only letters and numbers.");
    }
}