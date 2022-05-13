<?php

namespace Equit\Validation;

trait KnowsValidator
{
    private ?Validator $m_validator = null;

    public function setValidator(Validator $validator): void
    {
        $this->m_validator = $validator;
    }

    public function validator(): ?Validator
    {
        return $this->m_validator;
    }
}