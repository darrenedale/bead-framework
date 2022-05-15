<?php

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

/**
 * Validator rule to ensure that a string matches a regular expression.
 *
 * Regular expressions are evaluated using PHP's `preg_*` functions.
 */
class RegEx implements Rule
{
    /** @var string The regular expression. */
    private string $m_pattern;

    /**
     * Initialise a new instance of the rule.
     *
     * @param string $pattern The regular expression.
     */
    public function __construct(string $pattern)
    {
        $this->setPattern($pattern);
    }

    /**
     * Fetch the regular expression.
     *
     * @return string The regular expression.
     */
    public function pattern(): string
    {
        return $this->m_pattern;
    }

    /**
     * Set the regular expression.
     *
     * @param $pattern string The regular expression.
     */
    public function setPattern(string $pattern): void
    {
        $this->m_pattern = $pattern;
    }

    /**
     * Check some data against the rule.
     *
     * The data must be a string that matches the regular expression.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data passes, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return is_string($data) && preg_match($this->pattern(), $data);
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
        return tr("The field %1 must match the pattern %2.", __FILE__, __LINE__, $field, $this->pattern());
    }
}
