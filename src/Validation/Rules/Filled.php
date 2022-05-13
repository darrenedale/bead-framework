<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class Filled implements Rule
{
    public function passes(string $field, $data): bool
    {
        return 0 === $data || 0.0 === $data || !empty($data);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must not be empty.", __FILE__, __LINE__, $field);
    }
}
