<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation\Rules;

use Bead\Validation\Rule;
use Bead\Helpers\Iterable as IterableHelpers;

use function Bead\Helpers\I18n\tr;

/**
 * Validator rule to ensure that some data is an array of items of a given type.
 */
class IsTypedArray extends IsArray
{
    /** @var string The type or class required for the array's items. */
    private string $requiredType;

    /** @var string The type defined by the rule. */
    private string $type;

    /**
     * Initialise a new instance of the rule.
     *
     * @param string $type The type or class that all items must match.
     */
    public function __construct(string $type)
    {
        $this->type = $type;

        // handle these as special cases since the types don't match what gettype() returns - by recognising both forms
        // we're future-proofing against PHP bringing gettype() into line with the actual types
        $this->requiredType = match($type) {
            "int", "integer" => gettype(42),
            "float", "double" => gettype(3.14),
            "boolean", "bool" => gettype(true),
            default => $type,
        };
    }

    /**
     * Check some data against the rule.
     *
     * @param string $field The field name of the data being checked.
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is an array, `false` otherwise.
     */
    public function passes(string $field, mixed $data): bool
    {
        return parent::passes($field, $data) && IterableHelpers\all($data, fn (mixed $value) => $this->isCorrectType($value));
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
        return tr("The %1 field must be an array of %2 values.", __FILE__, __LINE__, $field, $this->type);
    }

    /** Helper to check a value matches the requied type. */
    protected function isCorrectType(mixed $value): bool
    {
        return $this->requiredType === gettype($value) || (is_object($value) && get_class($value) === $this->requiredType);
    }
}
