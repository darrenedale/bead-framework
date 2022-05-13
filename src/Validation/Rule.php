<?php

namespace Equit\Validation;

interface Rule
{
    public function passes(string $field, $data): bool;
    public function message(string $field): string;
}
