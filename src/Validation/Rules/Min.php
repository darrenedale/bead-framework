<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\Rule;
use TypeError;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that some data is at least a given value.
 */
class Min implements Rule
{
    /** @var int|float The minimum value. */
    private int|float $m_min;

    /**
     * @param int|float|string $min
     *
     * @throws TypeError if $min is given as a string and is not a numeric value.
     */
    public function __construct(int|float|string $min)
    {
        $numericMin = filter_var($min, FILTER_VALIDATE_INT);

        if (false === $numericMin) {
            $numericMin = filter_var($min, FILTER_VALIDATE_FLOAT);

            if (false === $numericMin) {
                throw new TypeError("Argument for parameter \$min must be numeric.");
            }
        }

        $this->setMin($numericMin);
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
        assert(is_int($min) || is_float($min), (
        8 <= PHP_MAJOR_VERSION
            ? new TypeError("The minimum value must be numeric.")
            : "The minimum value must be numeric."
        ));
        $this->m_min = $min;
    }

    /**
     * Helper to check an int using the rule.
     *
     * @param int $data The int to check.
     *
     * @return bool `true` if the int is no less than _min_, `false` otherwise.
     */
    protected function intPasses(int $data): bool
    {
        return $data >= (int) $this->min();
    }

    /**
     * Helper to check a float using the rule.
     *
     * @param float $data The float to check.
     *
     * @return bool `true` if the float is no less than _min_, `false` otherwise.
     */
    protected function floatPasses(float $data): bool
    {
        return $data >= (float) $this->min();
    }

    /**
     * Helper to check a string using the rule.
     *
     * @param string $data The string to check.
     *
     * @return bool `true` if the string is no shorter than _min_ characters, `false` otherwise.
     */
    protected function stringPasses(string $data): bool
    {
        return mb_strlen($data, "UTF-8") >= ((int) $this->min());
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
        return count($data) >= ((int) $this->min());
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
        } elseif (is_float($data)) {
            return $this->floatPasses($data);
        } elseif (is_array($data)) {
            return $this->arrayPasses($data);
        } elseif (is_string($data)) {
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
        return tr("The %1 field must not be lower than %2.", __FILE__, __LINE__, $field, $this->min());
    }
}
