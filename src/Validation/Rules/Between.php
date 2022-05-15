<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;
use TypeError;

/**
 * Validator rule to ensure that some data is between two values.
 */
class Between implements Rule
{
    /** @var int | float The minimum value. */
    private $m_min;

    /** @var int | float The maximum value. */
    private $m_max;

    /**
     * @param int|float $min
     */
    public function __construct($min, $max)
    {
        foreach (["min", "max"] as $bound) {
            $numeric = filter_var($$bound, FILTER_VALIDATE_INT);

            if (false === $numeric) {
                $numeric = filter_var($$bound, FILTER_VALIDATE_FLOAT);

                if (false === $numeric) {
                    throw new TypeError("Argument for parameter \${$bound} must be numeric.");
                }
            }

            $this->{"set{$bound}"}($numeric);
        }
    }

    /**
     * @return int|float The minimum.
     */
    public function min()
    {
        return $this->m_min;
    }

    /**
     * Set the minimum value.
     *
     * @param $min int|float
     */
    public function setMin($min)
    {
        assert (is_int($min) || is_float($min),  (
        8 <= PHP_MAJOR_VERSION
            ? new TypeError("The minimum value must be numeric.")
            : "The minimum value must be numeric."
        ));
        $this->m_min = $min;
    }

    /**
     * @return int|float The maximum.
     */
    public function max()
    {
        return $this->m_max;
    }

    /**
     * Set the minimum value.
     *
     * @param $max int|float
     */
    public function setMax($max)
    {
        assert (is_int($max) || is_float($max),  (
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
     * @return bool `true` if the int is between _min_ and _max_, `false` otherwise.
     */
    protected function intPasses(int $data): bool
    {
        return (int) $this->min() <= $data && (int) $this->max() >= $data;
    }

    /**
     * Helper to check a float using the rule.
     *
     * @param float $data The float to check.
     *
     * @return bool `true` if the float is between _min_ and _max_, `false` otherwise.
     */
    protected function floatPasses(float $data): bool
    {
        return (float) $this->min() <= $data && (float) $this->max() >= $data;
    }

    /**
     * Helper to check a string using the rule.
     *
     * @param string $data The string to check.
     *
     * @return bool `true` if the string is between _min_ and _max_ characters in length, `false` otherwise.
     */
    protected function stringPasses(string $data): bool
    {
        $len = mb_strlen($data, "UTF-8");
        return (int) $this->min() <= $len && (int) $this->max() >= $len;
    }

    /**
     * Helper to check an array using the rule.
     *
     * @param array $data The array to check.
     *
     * @return bool `true` if the array has no fewer than _min_ elements, `false` otherwise.
     */
    protected function arrayPasses(array $data): bool
    {
        return ((int) $this->min()) <= count($data) && ((int) $this->max()) >= count($data);
    }

    /**
     * Check some data against the rule.
     *
     * The following situation constitute passes:
     * - the data is a string of at least _min_ characters
     * - the data is an array of at least _min_ elements
     * - the data is an int no smaller than _min_
     * - the data is a float no smaller than _min_
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
        return tr("The %1 field must be between %2 and %3 inclusive.", __FILE__, __LINE__, $field, $this->min(), $this->max());
    }
}