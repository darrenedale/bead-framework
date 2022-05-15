<?php

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\DatasetAwareRule;
use Equit\Validation\KnowsDataset;
use InvalidArgumentException;
use LogicException;

/**
 * Validator rule to ensure that some data is non-empty unless some other field is set to one of a given set of values.
 */
class RequiredUnless implements DatasetAwareRule
{
    use KnowsDataset;
    use ReferencesOtherField;
    use ChecksDataForEmptiness;

    /** @var array The set of values for the related field. */
    private array $m_otherFieldValues;

    /**
     * Initialise a new instance of the rule.
     *
     * @param string $otherField The other field to check with.
     * @param array $values The set of values that the other field can have.
     */
    public function __construct(string $otherField, array $values)
    {
        assert (!empty($values), (
            8 <= PHP_MAJOR_VERSION
            ? new InvalidArgumentException("Argument for parameter \$values must not be an empty array.")
            : "Argument for parameter \$values must not be an empty array."
        ));
        $this->setOtherField($otherField);
        $this->setOtherFieldValues($values);
    }

    /**
     * Set the set of values for the related field.
     *
     * @param array $values The values.
     */
    public function setOtherFieldValues(array $values): void
    {
        assert(0 < count($values), (
            8 <= PHP_MAJOR_VERSION
                ? new LogicException("Must have at least one value to use RequiredUnless rule.")
                : "Must have at least one value to use RequiredUnless rule."
        ));
        $this->m_otherFieldValues = $values;
    }

    /**
     * Fetch the set of values for the related field.
     *
     * @return array The values.
     */
    public function otherFieldValues(): array
    {
        return $this->m_otherFieldValues;
    }

    /**
     * Helper to check whether the related field has one of the specified values.
     *
     * @return bool `true` if it does, `false` otherwise.
     */
    protected function otherFieldValueMatches(): bool
    {
        $data = $this->dataset();
        return array_key_exists($this->otherField(), $data) && in_array($data[$this->otherField()], $this->otherFieldValues(), true);
    }

    /**
     * Check some data against the rule.
     *
     * The data passes the rule if the related field has one of the specified values or if the data tested is
     * not empty.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data passes, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return self::isFilled($data) || $this->otherFieldValueMatches();
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
        return tr("The %1 field must not be empty if the %2 field has its current value.", __FILE__, __LINE__, $field, $this->otherField());
    }
}
