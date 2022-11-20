<?php

/**
 * @author Darren Edale
 * @version 0.9.2
 * @date May 2022
 */

declare(strict_types=1);

namespace Bead\Validation;

/**
 * Interface for validation rules.
 */
interface Rule
{
    /**
     * Test whether some data passes the rule.
     *
     * @param string $field The field under validation.
     * @param mixed $data data under validation.
     *
     * @return bool `true` if the data passes the rule, `false` otherwise.
     */
    public function passes(string $field, $data): bool;

    /**
     * Fetch the default message for when the data does not pass the rule.
     *
     * @param string $field The field under validation.
     *
     * @return string The message.
     */
    public function message(string $field): string;
}
