<?php

/**
 * @author Darren Edale
 * @version 1.2.0
 * @date May 2022
 */

declare(strict_types=1);

namespace Equit\Validation\Rules;

trait ChecksDataForEmptiness
{
    /**
     * Check whether some data is non-empty.
     *
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is non-empty, `false` otherwise.
     */
    private static function isFilled($data): bool
    {
        return 0 === $data || 0.0 === $data || false === $data || !empty($data);
    }

    /**
     * Check whether some data is empty.
     *
     * Unlike PHP's empty(), this method does not consider 0, 0.0 or false to be empty.
     *
     * @param mixed $data The data to check.
     *
     * @return bool `true` if the data is empty, `false` otherwise.
     */
    private static function isEmpty($data): bool
    {
        return !self::isFilled($data);
    }
}
