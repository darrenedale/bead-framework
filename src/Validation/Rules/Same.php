<?php

namespace Equit\Validation\Rules;

use Equit\Validation\DatasetAwareRule;
use Equit\Validation\KnowsDataset;

class Same implements DatasetAwareRule
{
    use KnowsDataset;
    use ReferencesOtherField;

    public function __construct(string $otherField)
    {
        $this->setOtherField($otherField);
    }

    public function passes(string $field, $data): bool
    {
        return ($this->dataset()[$this->otherField()] ?? null) === $data;
    }

    public function message(string $field): string
    {
        return tr("The %1 field must be the same as the %2 field.", __FILE__, __LINE__, $field, $this->otherField());
    }
}