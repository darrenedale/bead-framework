<?php

namespace Equit\Validation;

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
