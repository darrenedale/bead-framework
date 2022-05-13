<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class Email implements Rule
{
    public function passes(string $field, $data): bool
    {
        return false !== filter_var($data, FILTER_VALIDATE_EMAIL);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be a valid email address.", __FILE__, __LINE__, $field);
    }
}