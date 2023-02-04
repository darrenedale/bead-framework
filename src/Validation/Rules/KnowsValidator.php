<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

namespace Bead\Validation\Rules;

use Bead\Validation\Validator;

/**
 * Trait for validation rules that are aware of the validator they belong to.
 */
trait KnowsValidator
{
    /** @var Validator|null The validator, `null` if not set. */
    private ?Validator $m_validator = null;

    /**
     * Set the validator.
     *
     * @param Validator $validator The validator.
     */
    public function setValidator(Validator $validator): void
    {
        $this->m_validator = $validator;
    }

    /**
     * Fetch the validator.
     *
     * @return Validator|null The validator, if set.
     */
    public function validator(): ?Validator
    {
        return $this->m_validator;
    }
}
