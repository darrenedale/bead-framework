<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

namespace Equit\Validation;

/**
 * Trait for validation rules that are aware of the full dataset the validator they belong to is validating.
 */
trait KnowsDataset
{
    /** @var array|null The dataset, `null` if not set. */
    private ?array $m_dataset;

    /**
     * Fetch the dataset.
     *
     * @return array|null The dataset, if set.
     */
    public function dataset(): ?array
    {
        return $this->m_dataset;
    }

    /**
     * Set the dataset.
     *
     * @param array $dataset The dataset.
     */
    public function setDataset(array $dataset): void
    {
        $this->m_dataset = $dataset;
    }
}
