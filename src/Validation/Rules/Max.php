<?php

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;
use TypeError;

/**
 * Validator rule to ensure that some data is at most a given value.
 */
class Max implements Rule
{
    /** @var int | float The maximum value. */
    private $m_max;

    /**
     * @param int|float $max
     */
    public function __construct($max)
    {
        $numericMax = filter_var($max, FILTER_VALIDATE_INT);

        if (false === $numericMax) {
            $numericMax = filter_var($max, FILTER_VALIDATE_FLOAT);

            if (false === $numericMax) {
                throw new TypeError("Argument for parameter \$max must be numeric.");
            }
        }

        $this->setMax($numericMax);
    }

    /**
     * @return int|float The maximum.
     */
    public function max()
    {
        return $this->m_max;
    }

    /**
     * Set the maximum value.
     *
     * @param $max int|float
     */
    public function setMax($max)
    {
        assert(is_int($max) || is_float($max), (
        8 <= PHP_MAJOR_VERSION
            ? new TypeError("The maximum value must be numeric.")
            : "The maximum value must be numeric."
        ));
        $this->m_max = $max;
    }

    /**
     * Helper to check an int using the rule.
     *
     * @param int $data The int to check.
     *
     * @return bool `true` if the int is no bigger than _max_, `false` otherwise.
     */
    protected function intPasses(int $data): bool
    {
        return $data <= (int)$this->max();
    }

    /**
     * Helper to check a float using the rule.
     *
     * @param float $data The float to check.
     *
     * @return bool `true` if the float is no bigger than _max_, `false` otherwise.
     */
    protected function floatPasses(float $data): bool
    {
        return $data <= (float)$this->max();
    }

    /**
     * Helper to check a string using the rule.
     *
     * @param string $data The string to check.
     *
     * @return bool `true` if the string is no longer than _max_ characters, `false` otherwise.
     */
    protected function stringPasses(string $data): bool
    {
        return mb_strlen($data, "UTF-8") <= ((int) $this->max());
    }

    /**
     * Helper to check an array using the rule.
     *
     * @param array $data The array to check.
     *
     * @return bool `true` if the array has no more than _max_ elements, `false` otherwise.
     */
    protected function arrayPasses(array $data): bool
    {
        return count($data) <= ((int) $this->max());
    }

    /**
     * Check some data against the rule.
     *
     * The following situation constitute passes:
     * - the data is a string of at most _max_ characters
     * - the data is an array of at most _max_ elements
     * - the data is an int no bigger than _max_
     * - the data is a float no bigger than _max_
     *
     * Anything else is a fail.
     *
     * Strings are assumed to be UTF-8 encoded.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data passes, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        if (is_int($data)) {
            return $this->intPasses($data);
        } else if (is_float($data)) {
            return $this->floatPasses($data);
        } else if (is_array($data)) {
            return $this->arrayPasses($data);
        } else if (is_string($data)) {
            $intData = filter_var($data, FILTER_VALIDATE_INT);

            if (false !== $intData) {
                return $this->intPasses($intData);
            }

            $floatData = filter_var($data, FILTER_VALIDATE_FLOAT);

            if (false !== $floatData) {
                return $this->floatPasses($floatData);
            }

            return $this->stringPasses($data);
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
        return tr("The %1 field must not be greater than %2.", __FILE__, __LINE__, $field, $this->max());
    }
}
