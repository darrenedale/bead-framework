<?php

namespace Equit\Validation\Rules;

trait ReferencesOtherField
{
    private string $m_otherField;

    public function otherField(): string
    {
        return $this->m_otherField;
    }

    public function setOtherField(string $otherField): void
    {
        $this->m_otherField = $otherField;
    }
}
