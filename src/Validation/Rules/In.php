<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;

/**
 * Validator rule to ensure that some data is in a given set of values.
 */
class In implements Rule
{
    /** @var array The valid values for the tested data. */
    private array $m_options;

    /**
     * Initialise a new rule instance.
     *
     * @param array $options The valid values.
     */
    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    /**
     * Fetch the valid options.
     *
     * @return array The options.
     */
    public function options(): array
    {
        return $this->m_options;
    }

    /**
     * Set the valid options.
     *
     * @param array $options The options.
     */
    public function setOptions(array $options): void
    {
        $this->m_options = $options;
    }

    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is in the set of valid options, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return in_array($data, $this->options());
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
        return tr("The %1 field must be one of the specified options.", __FILE__, __LINE__, $field);
    }
}