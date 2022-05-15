<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

namespace Equit\Validation;

/**
 * Trait for validation rules that are aware of the validator they belong to.
 */
trait KnowsValidator
{
    /** @var \Equit\Validation\Validator|null The validator, `null` if not set. */
    private ?Validator $m_validator = null;

    /**
     * Set the validator.
     *
     * @param \Equit\Validation\Validator $validator The validator.
     */
    public function setValidator(Validator $validator): void
    {
        $this->m_validator = $validator;
    }

    /**
     * Fetch the validator.
     *
     * @return \Equit\Validation\Validator|null The validator, if set.
     */
    public function validator(): ?Validator
    {
        return $this->m_validator;
    }
}
