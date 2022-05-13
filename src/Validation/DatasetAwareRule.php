<?php

namespace Equit\Validation;

interface DatasetAwareRule extends Rule
{
    public function setDataset(array $dataset): void;
    public function dataset(): ?array;
}
