<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class IsTrue implements Rule
{
    public function passes(string $field, $data): bool
    {
        return true === filter_var($data, FILTER_VALIDATE_BOOLEAN);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be true.", __FILE__, __LINE__, $field);
    }
}
