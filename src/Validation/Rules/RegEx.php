<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class RegEx implements Rule
{
    private string $m_pattern;

    public function __construct(string $pattern)
    {
        $this->setPattern($pattern);
    }

    public function pattern(): string
    {
        return $this->m_pattern;
    }

    public function setPattern(string $pattern): void
    {
        $this->m_pattern = $pattern;
    }

    public function passes(string $field, $data): bool
    {
        return is_string($data) && preg_match($this->pattern(), $data);
    }

    public function message(string $field): string
    {
        return tr("The field %1 must match the pattern %2.", __FILE__, __LINE__, $field, $this->pattern());
    }
}