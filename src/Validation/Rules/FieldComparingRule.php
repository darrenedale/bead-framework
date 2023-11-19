<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use DateTime;
use Bead\Exceptions\ValidationRuleException;
use Bead\Validation\DatasetAwareRule;
use Exception;

/**
 * Base class for rules that define comparative relationships between a piece of data and the value int another field in
 * the dataset.
 */
abstract class FieldComparingRule implements DatasetAwareRule
{
    use KnowsDataset;
    use ReferencesOtherField;

    /**
     * Initialse a new instance of the rule.
     *
     * @param string $otherField The other field that will be used for comparison.
     */
    public function __construct(string $otherField)
    {
        $this->setOtherField($otherField);
    }

    /**
     * @return mixed|null The value, or `null` if the other field does not exist.
     * @throws ValidationRuleException If the other field is not present in the dataset.
     */
    protected function otherValue()
    {
        $data = $this->dataset();

        if (!array_key_exists($this->otherField(), $data)) {
            throw new ValidationRuleException($this, "The referenced field '{$this->otherField()}' is not present in the datase.");
        }

        return $data[$this->otherField()];
    }

    /**
     * Fetch the value in the referenced field as an integer.
     *
     * @return int The int value.
     * @throws ValidationRuleException if the referenced field does not contain an integer.
     */
    protected function otherValueAsInt(): int
    {
        $max = filter_var($this->otherValue(), FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE,]);

        if (!isset($max)) {
            throw new ValidationRuleException($this, "Value for other field to compare with the data is not an integer.");
        }

        return $max;
    }

    /**
     * Fetch the value in the referenced field as a float/double.
     *
     * @return float The floating point value.
     * @throws ValidationRuleException if the referenced field does not contain a float/double.
     */
    protected function otherValueAsFloat(): float
    {
        $max = filter_var($this->otherValue(), FILTER_VALIDATE_FLOAT, ["flags" => FILTER_NULL_ON_FAILURE,]);

        if (!isset($max)) {
            throw new ValidationRuleException($this, "Value for other field to compare with the data is not a float.");
        }

        return $max;
    }

    /**
     * Fetch the value in the referenced field as a DateTime.
     *
     * @return DateTime The DateTime
     * @throws ValidationRuleException if the referenced field does not contain a DateTime.
     */
    protected function otherValueAsDateTime(): DateTime
    {
        try {
            $max = new DateTime($this->otherValue());
        } catch (Exception $err) {
            throw new ValidationRuleException($this, "Value for other field to compare with the data is not a date/time.", 0, $err);
        }

        return $max;
    }

    /**
     * Helper to check a int using the rule.
     *
     * Subclasses must implement the rule's logic for ints in this method.
     *
     * @param int $data The int to check.
     *
     * @return bool `true` if the int satisfies the relationship to the value in the referenced field, `false`
     * otherwise.
     * @throws ValidationRuleException if the referenced field does not contain an int.
     */
    abstract protected function intPasses(int $data): bool;

    /**
     * Helper to check a float using the rule.
     *
     * Subclasses must implement the rule's logic for floats in this method.
     *
     * @param float $data The float to check.
     *
     * @return bool `true` if the float satisfies the relationship to the value in the referenced field, `false`
     * otherwise.
     * @throws ValidationRuleException if the referenced field does not contain a float.
     */
    abstract protected function floatPasses(float $data): bool;

    /**
     * Helper to check a string using the rule.
     *
     * Subclasses must implement the rule's logic for strings in this method.
     *
     * @param string $data The string to check.
     *
     * @return bool `true` if the string satisfies the relationship to the number of characters specified in the
     * referenced field, `false` otherwise.
     * @throws ValidationRuleException if the referenced field does not contain an integer.
     */
    abstract protected function stringPasses(string $data): bool;

    /**
     * Helper to check an array using the rule.
     *
     * Subclasses must implement the rule's logic for arrays in this method.
     *
     * @param array $data The array to check.
     *
     * @return bool `true` if the array satisfies the relationship to the number of elements specified in the referenced
     * field, `false` otherwise.
     * @throws ValidationRuleException if the referenced field does not contain an integer.
     */
    abstract protected function arrayPasses(array $data): bool;

    /**
     * Helper to check a DateTime using the rule.
     *
     * Subclasses must implement the rule's logic for DateTime objects in this method.
     *
     * @param DateTime $data The DateTime to check.
     *
     * @return bool `true` if the DateTime satisfies the relationship to the DateTime in the referenced field.
     * @throws ValidationRuleException if the referenced field does not contain a DateTime.
     */
    abstract protected function dateTimePasses(DateTime $data): bool;

    /**
     * Check some data against the rule.
     *
     * Strings are assumed to be UTF-8 encoded. If the data is a string, attempts will be made to convert to the
     * following types, in this order:
     * - int
     * - float
     * - DateTime
     *
     * If none of the conversions succeeds, it will be treated as a string (i.e. its length will be compared to the
     * referenced field value).
     *
     * The following situation constitute passes:
     * - the data is an int that satisfies the relationship to the int value in the referenced field.
     * - the data is a float that satisfies the relationship to the float value in the referenced field
     * - the data is a DateTime that satisfies the relationship to the DateTime value in the referenced field
     * - the data is a string of a number of characters that that satisfies the relationship to the int value in the
     *   referenced field.
     * - the data is an array of a number of elements that satisfies the relationship to the int value in the referenced
     *   field.
     *
     * Anything else is a fail.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data passes, `false` otherwise.
     * @throws ValidationRuleException if the referenced field does not contain a value that can be used to constrain
     * the data provided.
     */
    public function passes(string $field, $data): bool
    {
        if (is_int($data)) {
            return $this->intPasses($data);
        } elseif (is_float($data)) {
            return $this->floatPasses($data);
        } elseif (is_array($data)) {
            return $this->arrayPasses($data);
        } elseif ($data instanceof DateTime) {
            return $this->dateTimePasses($data);
        } elseif (is_string($data)) {
            $intData = filter_var($data, FILTER_VALIDATE_INT);

            if (false !== $intData) {
                return $this->intPasses($intData);
            }

            $floatData = filter_var($data, FILTER_VALIDATE_FLOAT);

            if (false !== $floatData) {
                return $this->floatPasses($floatData);
            }

            try {
                $data = new DateTime($data);
            } catch (Exception $err) {
                // do nothing, we'll just move on to testing as a string
            }

            // NOTE keep this out of the try{} block: if it throws, we want that exception to be thrown to the caller,
            // the catch block is only for exceptions thrown by the DateTime constructor
            if ($data instanceof DateTime) {
                return $this->dateTimePasses($data);
            }

            return $this->stringPasses($data);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    abstract public function message(string $field): string;
}
