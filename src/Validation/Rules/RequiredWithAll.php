<?php

namespace Equit\Validation\Rules;

use Equit\Validation\DatasetAwareRule;
use Equit\Validation\KnowsDataset;
use function Equit\Traversable\all;

/**
 * Data must not be empty if all of a set of other fields is are not empty.
 */
class RequiredWithAll implements DatasetAwareRule
{
    use KnowsDataset;
    use ReferencesOtherFields;

    private array $m_otherFieldValues;

    public function __construct(array $otherFields)
    {
        assert(!empty($otherFields), new \InvalidArgumentException("Argument for parameter \$otherFields cannot be an empty array."));
        $this->setOtherFields($otherFields);
    }

    protected function otherFieldsArePresent(): bool
    {
        $data = $this->dataset();
        return all($this->otherFields(), fn(string $field): bool => !empty($data[$field]));
    }

    public function passes(string $field, $data): bool
    {
        return !empty($data) || !$this->otherFieldsArePresent();
    }

    public function message(string $field): string
    {
        if (1 == count($this->otherFields())) {
            return tr("The %1 field must not be empty if the %2 field is filled.", __FILE__, __LINE__, $field, $this->otherFields()[0]);
        }

        return tr(
            "The %1 field must not be empty if the %2 fields are all filled.",
            __FILE__,
            __LINE__,
            $field,
            grammaticalImplode($this->otherFields(), ", ", tr(" and "))
        );
    }
}