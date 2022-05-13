<?php

namespace Equit\Validation;

interface ValidatorAwareRule extends Rule
{
    public function setValidator(Validator $validator): void;
    public function validator(): ?Validator;
}
