<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;
use Throwable;

class Json implements Rule
{
    public function passes(string $field, $data): bool
    {
        try {
            return is_string($data) && json_decode($data, false, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $err) {
            return false;
        }
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be valid JSON.", __FILE__, __LINE__, $field);
    }
}