<?php

namespace Equit\Validation;

trait KnowsDataset
{
    private ?array $m_dataset;

    public function dataset(): ?array
    {
        return $this->m_dataset;
    }

    public function setDataset(array $dataset): void
    {
        $this->m_dataset = $dataset;
    }
}