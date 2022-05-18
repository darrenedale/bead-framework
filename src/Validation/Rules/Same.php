<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use Equit\Validation\DatasetAwareRule;
use Equit\Validation\Rules\KnowsDataset;

/**
 * Validator rule to ensure that some data is the same as the data for another field.
 */
class Same implements DatasetAwareRule
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
     * The data passes the rule if it is the same as the related field's value.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data passes, `false` otherwise.
     */
    public function passes(string $field, $data): bool
    {
        return ($this->dataset()[$this->otherField()] ?? null) === $data;
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
        return tr("The %1 field must be the same as the %2 field.", __FILE__, __LINE__, $field, $this->otherField());
    }
}