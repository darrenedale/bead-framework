<?php

namespace Equit\Validation\Rules;

use Equit\Validation\DatasetAwareRule;
use Equit\Validation\KnowsDataset;
use InvalidArgumentException;
use function Equit\Traversable\some;

class RequiredWith implements DatasetAwareRule
{
    use KnowsDataset;
    use ReferencesOtherFields;

    private array $m_otherFieldValues;

    public function __construct(array $otherFields)
    {
        assert (!empty($otherFields), new InvalidArgumentException("Argument for parameter \$otherFields must not be an empty array."));
        $this->setOtherFields($otherFields);
    }

    protected function otherFieldIsPresent(): bool
    {
        $data = $this->dataset();
        return some($this->otherFields(), fn(string $field): bool => !empty($data[$field]));
    }

    public function passes(string $field, $data): bool
    {
        return !empty($data) || !$this->otherFieldIsPresent();
    }

    public function message(string $field): string
    {
        return tr(
            "The %1 field must not be empty if the %2 field is filled.",
            __FILE__,
            __LINE__,
            $field,
            (
                1 == count($this->otherFields())
                ? $this->otherFields()[0]
                : grammaticalImplode($this->otherFields(), ", ", tr(" or "))
            )
        );
    }
}