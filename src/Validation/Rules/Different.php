<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types = 1);

namespace Bead\Validation\Rules;

use Bead\Validation\DatasetAwareRule;

/**
 * Validator rule to ensure that some data is different from the data for another field.
 */
class Different implements DatasetAwareRule
{
    use KnowsDataset;
    use ReferencesOtherField;

    /**
     * Initialise a new instance of the rule.
     *
     * @param string $otherField The field whose value must match the data.
     */
    public function __construct(string $otherField)
    {
        $this->setOtherField($otherField);
    }

    /**
     * Check some data against the rule.
     *
     * The data passes the rule if it is different from the related field's value.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data passes, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return ($this->dataset()[$this->otherField()] ?? null) !== $data;
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
        return tr("The %1 field must be different from the %2 field.", __FILE__, __LINE__, $field, $this->otherField());
    }
}