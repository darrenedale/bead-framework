<?php

declare(strict_types=1);

namespace Bead\Contracts;

/** Interface for accessing environment variables. */
interface Environment
{
    /**
     * Check whether an environment variable is set.
     *
     * @param string $name The name of the variable to check for.
     *
     * @return bool true if the environment has the named variable, false if not.
     */
    public function has(string $name): bool;

    /**
     * Fetch the value for an environment variable.
     *
     * @param string $name The name of the variable to fetch.
     *
     * @return string The value of the environment variable, or an empty string if it's not set.
     */
    public function get(string $name): string;

    /**
     * Fetch the names of all defined variables.
     *
     * @return string[]
     */
    public function names(): array;

    /**
     * Fetch all the environment variables.
     *
     * @return array<string,string>
     */
    public function all(): array;
}
