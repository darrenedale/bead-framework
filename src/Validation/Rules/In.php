<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\Rule;
use function Bead\Traversable\all;

/**
 * Validator rule to ensure that some data is in a given set of values.
 *
 * If the value under validation is an array, all of the values in the array must be in the option set; otherwise, the
 * value under validation must be in the option set.
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
		return (is_array($data) && all($data, fn($value) => in_array($value, $this->options()))) || in_array($data, $this->options());
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