<?php

namespace Equit\Validation\Rules;

use Equit\Validation\Rule;
use TypeError;

class Min implements Rule
{
    /** @var int | float The minimum value. */
    private $m_min;

    /**
     * @param int|float $min
     */
    public function __construct($min)
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
        assert (is_int($min) || is_float($min), new \TypeError("The minimum value must be numeric."));
        $this->m_min = $min;
    }

    protected function intPasses(string $field, int $data): bool
    {
        return $data >= (int) $this->min();
    }

    protected function floatPasses(string $field, float $data): bool
    {
        return $data >= (float) $this->min();
    }

    protected function stringPasses(string $field, string $data): bool
    {
        return strlen($data) >= ((int) $this->min());
    }

    protected function arrayPasses(string $field, array $data): bool
    {
        return count($data) >= ((int) $this->min());
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
        return tr("The %1 field must not be lower than %2.", __FILE__, __LINE__, $field, $this->min());
    }
}