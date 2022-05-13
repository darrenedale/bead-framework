<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class Ip implements Rule
{
    public function passes(string $field, $data): bool
    {
        return false !== filter_var($data, FILTER_VALIDATE_IP);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be a valid IP address.", __FILE__, __LINE__, $field);
    }
}