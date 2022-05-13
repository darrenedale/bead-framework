<?php

namespace Equit\Validation\Rules;

class IsArray implements \Equit\Validation\Rule
{
    public function passes(string $field, $data): bool
    {
        return is_array($data);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be an array.", __FILE__, __LINE__, $field);
    }
}