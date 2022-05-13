<?php

namespace Equit\Validation\Rules;

use Equit\Validation\TypeConvertingRule;

class Integer implements TypeConvertingRule
{
    public function passes(string $field, $data): bool
    {
        return false !== filter_var($data, FILTER_VALIDATE_INT);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be an integer.", __FILE__, __LINE__, $field);
    }

    public function convert($data): int
    {
        return filter_var($data, FILTER_VALIDATE_INT);
    }
}