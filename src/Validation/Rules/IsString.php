<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class IsString implements Rule
{
    public function passes(string $field, $data): bool
    {
        return is_string($data);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be a string.", __FILE__, __LINE__, $field);
    }
}