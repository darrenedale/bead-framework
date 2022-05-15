<?php

namespace Equit\Validation;

/**
 * Interface for rules that convert the type of validated data.
 *
 * For example, the Date rule will accept a date as a string, and will convert it to a DateTime object if it passes
 * validation. This only affects the data returned by `Validator::validated()`, the original data is always unmodified.
 */
interface TypeConvertingRule extends Rule
{
    /**
     * Convert the provided data to the appropriate type.
     *
     * This should only be called once the Rule has indicated the data is valid.
     *
     * @param mixed $data The data to convert.
     *
     * @return mixed The converted data.
     */
    public function convert($data);
}
