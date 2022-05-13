<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;
use Equit\Validation\TypeConvertingRule;

class Number implements TypeConvertingRule
{
    public function passes(string $field, $data): bool
    {
        return false !== filter_var($data, FILTER_VALIDATE_FLOAT);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be an number.", __FILE__, __LINE__, $field);
    }

    public function convert($data): float
    {
        return filter_var($data, FILTER_VALIDATE_FLOAT);
    }
}