<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

/**
 * Trait for rules that implement validation logic that compares to another field in the dataset in some way.
 */
trait ReferencesOtherField
{
    /** @var string The other field that is related to the rule. */
    private string $m_otherField;

    /**
     * The other field that the rule relates to.
     *
     * @return string The field.
     */
    public function otherField(): string
    {
        return $this->m_otherField;
    }

    /**
     * Set the other field that the rule relates to.
     *
     * @param string The field.
     */
    public function setOtherField(string $otherField): void
    {
        $this->m_otherField = $otherField;
    }
}
