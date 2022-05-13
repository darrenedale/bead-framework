<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;
use TypeError;

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
        assert(is_int($max) || is_float($max), new \TypeError("The maximum value must be numeric."));
        $this->m_max = $max;
    }

    protected function intPasses(string $field, int $data): bool
    {
        return $data <= (int)$this->max();
    }

    protected function floatPasses(string $field, float $data): bool
    {
        return $data <= (float)$this->max();
    }

    protected function stringPasses(string $field, string $data): bool
    {
        return strlen($data) <= ((int)$this->max());
    }

    protected function arrayPasses(string $field, array $data): bool
    {
        return count($data) <= ((int) $this->max());
    }

    public function passes(string $field, $data): bool
    {
        if (is_int($data)) {
            return $this->intPasses($field, $data);
        } else if (is_float($data)) {
            return $this->floatPasses($field, $data);
        } else if (is_array($data)) {
            return $this->arrayPasses($field, $data);
        } else if (is_string($data)) {
            $intData = filter_var($data, FILTER_VALIDATE_INT);

            if (false !== $intData) {
                return $this->intPasses($field, $intData);
            }

            $floatData = filter_var($data, FILTER_VALIDATE_FLOAT);

            if (false !== $floatData) {
                return $this->floatPasses($field, $floatData);
            }

            return $this->stringPasses($field, $data);
        }

        return false;
    }

    public function message(string $field): string
    {
        return tr("The %1 field must not be greater than %2.", __FILE__, __LINE__, $field, $this->max());
    }
}