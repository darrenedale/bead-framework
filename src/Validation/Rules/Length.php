<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class Length implements Rule
{
    private int $m_length;

    public function __construct(int $length)
    {
        $this->setLength($length);
    }

    public function length(): int
    {
        return $this->m_length;
    }

    public function setLength(int $length): void
    {
        $this->m_length = $length;
    }

    public function passes(string $field, $data): bool
    {
        if (is_string($data)) {
            return $this->length() === strlen($data);
        } else if (is_array($data)) {
            return $this->length() === count($data);
        }

        return false;
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be %2 in length.", __FILE__, __LINE__, $field, $this->length());
    }
}