<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\Rule;
use Countable;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that some data is of at least a given length.
 *
 * This is a little like the Min rule, but it won't coerce a string containing a numeric value to a number. Use this
 * when you definitely want to check the length rather than checking a contained value.
 */
class MinLength implements Rule
{
    /** @var int The minimum length. */
    private int $m_length;

    /**
     * Initialise a new instance of the rule.
     *
     * @param int $length The min length.
     */
    public function __construct(int $length)
    {
        $this->setLength($length);
    }

    /**
     * Fetch the min length.
     *
     * @return int The min length.
     */
    public function length(): int
    {
        return $this->m_length;
    }

    /**
     * Set the min length.
     *
     * @param int $length The min length.
     */
    public function setLength(int $length): void
    {
        $this->m_length = $length;
    }

    /**
     * Check some data against the rule.
     *
     * A string of at least the min length will pass, as will an array or Countable with at least the min number of
     * elements. Anything else will fail.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is a string or array of at least the min length, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        if (is_string($data)) {
            return $this->length() <= strlen($data);
        } elseif (is_array($data) || $data instanceof Countable) {
            return $this->length() <= count($data);
        }

        return false;
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
        return tr("The %1 field must be at least %2 in length.", __FILE__, __LINE__, $field, $this->length());
    }
}
