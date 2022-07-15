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
 * Validator rule to ensure that some data is of a given length.
 */
class Length implements Rule
{
    /** @var int The required length. */
    private int $m_length;

    /**
     * Initialise a new instance of the rule.
     *
     * @param int $length The required length.
     */
    public function __construct(int $length)
    {
        $this->setLength($length);
    }

    /**
     * Fetch the required length.
     *
     * @return int The required length.
     */
    public function length(): int
    {
        return $this->m_length;
    }

    /**
     * Set the required length.
     *
     * @param int $length The required length.
     */
    public function setLength(int $length): void
    {
        $this->m_length = $length;
    }

    /**
     * Check some data against the rule.
     *
     * A string of the required length will pass, as will an array with the required number of elements. Anything else
     * will fail.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is a string or array of the required length, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        if (is_string($data)) {
            return $this->length() === strlen($data);
        } else if (is_array($data) || $data instanceof Countable) {
            return $this->length() === count($data);
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
        return tr("The %1 field must be %2 in length.", __FILE__, __LINE__, $field, $this->length());
    }
}
