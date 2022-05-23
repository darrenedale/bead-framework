<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

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
