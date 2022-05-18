<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

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
     * @param Validator $validator The validator.
     */
    public function setValidator(Validator $validator): void;

    /**
     * Fetch the validator.
     *
     * @return Validator|null The validator, if set.
     */
    public function validator(): ?Validator;
}
