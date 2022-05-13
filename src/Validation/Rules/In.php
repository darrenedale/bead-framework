<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

class In implements Rule
{
    private array $m_options;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function passes(string $field, $data): bool
    {
        return in_array($data, $this->options());
    }

    public function options(): array
    {
        return $this->m_options;
    }

    public function setOptions(array $options): void
    {
        $this->m_options = $options;
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be one of the specified options.");
    }
}