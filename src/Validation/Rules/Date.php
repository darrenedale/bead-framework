<?php

namespace Equit\Validation\Rules;

use DateTime;
use Equit\Validation\TypeConvertingRule;

class Date implements TypeConvertingRule
{

    public function passes(string $field, $data): bool
    {
        return $data instanceof DateTime || false !== strtotime($data);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be a valid date.", __FILE__, __LINE__, $field);
    }

    public function convert($data): DateTime
    {
        return ($data instanceof DateTime ? $data : new DateTime("@" . strtotime($data)));
    }
}