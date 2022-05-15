<?php

namespace Equit\Validation;

/**
 * Interface for rules that are aware of the full dataset the validator they belong to is validating.
 *
 * The `KnowsDataset` trait is an easy way to implement this interface in your Rule classes.
 */
interface DatasetAwareRule extends Rule
{
    /**
     * Set the dataset.
     *
     * @param array $dataset The dataset.
     */
    public function setDataset(array $dataset): void;

    /**
     * Fetch the dataset.
     *
     * @return array|null The dataset, if set.
     */
    public function dataset(): ?array;
}
