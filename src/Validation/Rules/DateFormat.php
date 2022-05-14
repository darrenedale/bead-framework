<?php

namespace Equit\Validation\Rules;

use DateTime;
use Equit\Validation\Rule;

class DateFormat implements Rule
{
    private string $m_format;

    public function __construct(string $format)
    {
        $this->setFormat($format);
    }

    public function setFormat(string $format): void
    {
        $this->m_format = $format;
    }

    public function format(): string
    {
        return $this->m_format;
    }

    public function passes(string $field, $data): bool
    {
        return is_string($data) && false !== DateTime::createFromFormat($this->format(), $data);
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be a date/time string formatted as %2.", __FILE__, __LINE__, $field, $this->format());
    }
}