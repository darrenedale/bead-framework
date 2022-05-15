<?php

namespace Equit\Validation;

/**
 * Interface for rules that are aware of the validator they belong to.
 *
 * The `KnowsValidator` trait is an easy way to implement this interface in your Rule classes.
 */
interface ValidatorAwareRule extends Rule
{
    /**
     * Set the validator.
     *
     * @param \Equit\Validation\Validator $validator The validator.
     */
    public function setValidator(Validator $validator): void;

    /**
     * Fetch the validator.
     *
     * @return \Equit\Validation\Validator|null The validator, if set.
     */
    public function validator(): ?Validator;
}
