<?php

namespace Equit\Validation\Rules;

/**
 * Validator rule to ensure that a key is present in the data.
 *
 * The key may have any value, including null, as long as it exists in the dataset.
 */
class Present implements \Equit\Validation\DatasetAwareRule
{
	use KnowsDataset;

	/**
	 * @inheritDoc
	 */
	public function passes(string $field, $data): bool
	{
		return array_key_exists($field, $this->dataset());
	}

	/**
	 * @inheritDoc
	 */
	public function message(string $field): string
	{
		return tr("The %1 field must be present in the data.", __FILE__, __LINE__, $field);
	}
}