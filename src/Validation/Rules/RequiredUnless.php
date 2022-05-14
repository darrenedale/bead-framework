<?php

namespace Equit\Validation\Rules;

use Equit\Validation\DatasetAwareRule;
use Equit\Validation\KnowsDataset;
use InvalidArgumentException;
use LogicException;

class RequiredUnless implements DatasetAwareRule
{
    use KnowsDataset;
    use ReferencesOtherField;

    private array $m_otherFieldValues;

    public function __construct(string $otherField, array $values)
    {
        assert (!empty($values), new InvalidArgumentException("Argument for parameter \$values must not be an empty array."));
        $this->setOtherField($otherField);
        $this->setOtherFieldValues($values);
    }

    public function setOtherFieldValues(array $values): void
    {
        assert(0 < count($values), new LogicException("Must have at least one value to use RequiredIf rule."));
        $this->m_otherFieldValues = $values;
    }

    public function otherFieldValues(): array
    {
        return $this->m_otherFieldValues;
    }

    protected function otherFieldValueMatches(): bool
    {
        $data = $this->dataset();
        return array_key_exists($this->otherField(), $data) && in_array($data[$this->otherField()], $this->otherFieldValues(), true);
    }

    public function passes(string $field, $data): bool
    {
        return !empty($data) || $this->otherFieldValueMatches();
    }

    public function message(string $field): string
    {
        return tr("The %1 field must not be empty if the %2 field has its current value.", __FILE__, __LINE__, $field, $this->otherField());
    }
}