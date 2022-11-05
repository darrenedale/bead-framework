<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

use InvalidArgumentException;
use function Equit\Helpers\Iterable\all;

/**
 * Trait for rules that implement validation logic that compares to multiple other fields in the dataset in some way.
 */
trait ReferencesOtherFields
{
    /** @var array<string> The other fields. */
    private array $m_otherFields;

    /**
     * Fetch the other fields.
     *
     * @return array<string> The other fields.
     */
    public function otherFields(): array
    {
        return $this->m_otherFields;
    }

    /**
     * Set the other fields.
     *
     * @param array<string> $otherFields The other fields.
     */
    public function setOtherFields(array $otherFields): void
    {
        assert(all($otherFields, "is_string"), (
            8 <= PHP_MAJOR_VERSION
            ? new InvalidArgumentException("The array argument for parameter \$otherFields must contain only strings.")
            : "The array argument for parameter \$otherFields must contain only strings."
        ));
        $this->m_otherFields = $otherFields;
    }
}
