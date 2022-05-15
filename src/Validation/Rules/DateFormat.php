<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use DateTime;
use Equit\Validation\Rule;

/**
 * Validator rule to ensure that some data is a date string in a specified format.
 *
 * The rule supports all formats that PHP's `DateTime::createFromFormat()` supports.
 */
class DateFormat implements Rule
{
    /** @var string The required date format. */
    private string $m_format;

    /**
     * Initialise a new rule instance.
     *
     * @param string $format The required date format.
     */
    public function __construct(string $format)
    {
        $this->setFormat($format);
    }

    /**
     * Set the required date format.
     *
     * @param string $format The format.
     */
    public function setFormat(string $format): void
    {
        $this->m_format = $format;
    }

    /**
     * Fetch the required date format.
     *
     * @return string The format.
     */
    public function format(): string
    {
        return $this->m_format;
    }

    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is a date string in the required format, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return is_string($data) && false !== DateTime::createFromFormat($this->format(), $data);
    }

    /**
     * Fetch the default message for when the data does not pass the rule.
     *
     * @param string $field The field under validation.
     *
     * @return string The message.
     */
    public function message(string $field): string
    {
        return tr("The %1 field must be a date/time string formatted as %2.", __FILE__, __LINE__, $field, $this->format());
    }
}
