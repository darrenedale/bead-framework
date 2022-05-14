<?php

namespace Equit\Validation\Rules;

use function Equit\Traversable\all;

trait ReferencesOtherFields
{
    /** @var array<string> */
    private array $m_otherFields;

    /**
     * @return array<string>
     */
    public function otherFields(): array
    {
        return $this->m_otherFields;
    }

    /**
     * @param array<string> $otherFields
     */
    public function setOtherFields(array $otherFields): void
    {
        assert(all($otherFields, "is_string"), new \InvalidArgumentException("The array argument for parameter \$otherFields must contain only strings."));
        $this->m_otherFields = $otherFields;
    }
}
