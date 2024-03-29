<?php

namespace Bead\Polyfill
{
    /**
     * Determine whether one string starts with another.
     *
     * This is a polyfill for the built-in function introduced in PHP 8.
     *
     * @param string $haystack The string that is being checked.
     * @param string $needle The string it must start with.
     *
     * @return bool true if the string does start with the other, false otherwise.
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        return "" === $needle || (strlen($haystack) >= strlen($needle) && $needle === substr($haystack, 0, strlen($needle)));
    }

    /**
     * Determine whether one string ends with another.
     *
     * This is a polyfill for the built-in function introduced in PHP 8.
     *
     * @param string $haystack The string that is being checked.
     * @param string $needle The string it must end with.
     *
     * @return bool true if the string does end with the other, false otherwise.
     */
    function str_ends_with(string $haystack, string $needle): bool
    {
        return "" === $needle || (strlen($haystack) >= strlen($needle) && $needle === substr($haystack, -strlen($needle)));
    }

    /**
     * Determine whether one string contains another.
     *
     * This is a polyfill for the built-in function introduced in PHP 8.
     *
     * @param string $haystack The string that is being checked.
     * @param string $needle The string it must contain.
     *
     * @return bool true if the string does contain the other, false otherwise.
     */
    function str_contains(string $haystack, string $needle): bool
    {
        return "" === $needle || false !== strpos($haystack, $needle);
    }
}

namespace {
    if (!function_exists("str_starts_with")) {
        function str_starts_with(string $haystack, string $needle): bool
        {
            return Bead\Polyfill\str_starts_with($haystack, $needle);
        }
    }

    if (!function_exists("str_ends_with")) {
        function str_ends_with(string $haystack, string $needle): bool
        {
            return Bead\Polyfill\str_ends_with($haystack, $needle);
        }
    }

    if (!function_exists("str_contains")) {
        function str_contains(string $haystack, string $needle): bool
        {
            return Bead\Polyfill\str_contains($haystack, $needle);
        }
    }
}
