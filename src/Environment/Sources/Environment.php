<?php

declare(strict_types=1);

namespace Bead\Environment\Sources;

use Bead\Contracts\Environment as EnvironmentContract;

use function array_keys;
use function getenv;

/** Source environment variables from the host environment. */
class Environment implements EnvironmentContract
{
    /**
     * Determine whether the environment contains a given variable.
     *
     * @param string $name The variable name to check for.
     *
     * @return bool true if the variable is defined, false if not.
     */
    public function has(string $name): bool
    {
        return "" !== $this->get($name);
    }

    /**
     * Fetch a value from the environment.
     *
     * @param string $name The name of the variable to fetch.
     *
     * @return string The value, or an empty string if the variable is not defined.
     */
    public function get(string $name): string
    {
        return (string) (getenv($name) ?? "");
    }

    /**
     * Fetch the names of all defined variables.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys(getenv());
    }

    /**
     * Fetch all the environment variables.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        return getenv();
    }
}
