<?php

declare(strict_types=1);

namespace Bead\Environment\Sources;

use Bead\Contracts\Environment;
use Bead\Exceptions\Environment\Exception as EnvironmentException;

use function is_float;
use function is_int;
use function is_string;
use function Bead\Helpers\Iterable\all;

/** An environment variable source that uses a PHP associative array to provide named values. */
class StaticArray implements Environment
{
    use ValidatesVariableNames;

    /** @var array<string,string>  */
    private array $data = [];

    /**
     * Initialise a new StaticArray environment provider.
     *
     * @param array<string,string|int|float> $data The environment variables.
     * @throws EnvironmentException
     */
    public function __construct(array $data)
    {
        if (!all($data, fn(mixed $value): bool => is_string($value) || is_int($value) || is_float($value))) {
            throw new EnvironmentException("Values for environment variable arrays must be ints, floats or strings.");
        }

        foreach ($data as $key => $value) {
            $name = self::validateVariableName((string) $key);

            if (!isset($name)) {
                throw new EnvironmentException("'{$key}' is not a valid environment variable name.");
            }

            $this->data[$name] = (string) $value;
        }
    }

    /**
     * Determine whether a given environment variable is set.
     *
     * @param string $name The name of the variable to check for.
     *
     * @return bool true if it's set, false if not.
     */
    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Fetch a named variable's value.
     *
     * @param string $name The name of the variable to fetch.
     *
     * @return string The value, or an empty string if it's not set..
     */
    public function get(string $name): string
    {
        return (string) ($this->data[$name] ?? "");
    }
}
