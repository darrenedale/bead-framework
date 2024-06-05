<?php

declare(strict_types=1);

namespace Bead\Environment;

use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Exceptions\EnvironmentException;
use Bead\Facades\Log;

use function array_key_exists;
use function array_keys;
use function array_unshift;
use function Bead\Helpers\Iterable\some;
use function in_array;

/**
 * Provide flexible access to environment variables.
 *
 * The variables are read from one or more sources. Typically, you will set up an instance of this with a source that
 * reads the actual environment variables, and add extra sources to override/augment those values with variables from
 * other sources (for example .env files).
 *
 * The Environment service binder sets up an instance that is bound to the Environment contract, and is available using
 * the Environment facade. The sources for this service are defined in the env config file.
 */
class Environment implements EnvironmentContract
{
    /** @var EnvironmentContract[] The environment variable sources. */
    private array $sources = [];

    /**
     * Add a source to the environment.
     *
     * @param EnvironmentContract $provider The source to add.
     */
    public function addSource(EnvironmentContract $provider): void
    {
        array_unshift($this->sources, $provider);
    }

    /**
     * Check whether a key is present in one of the environment's sources.
     *
     * @param string $name The key to check.
     *
     * @return bool true if the key exists in one or more sources, false if not.
     */
    public function has(string $name): bool
    {
        return some($this->sources, fn (EnvironmentContract $provider) => $provider->has($name));
    }

    /**
     * Retrieve a value from the environment.
     *
     * The environment sources are queried in the reverse order in which they were added - more recently added sources
     * override earlier ones.
     *
     * @param string $name The key to fetch.
     *
     * @return string The environment value for the key, or an empty string if no source contains the key.
     */
    public function get(string $name): string
    {
        foreach ($this->sources as $source) {
            try {
                if ($source->has($name)) {
                    return $source->get($name);
                }
            } catch (EnvironmentException $err) {
                Log::warning("Environment exception querying environment source of type " . $source::class . ": {$err->getMessage()}");
            }
        }

        return "";
    }

    /**
     * Fetch the names of all defined variables.
     *
     * @return string[]
     */
    public function names(): array
    {
        $names = [];

        foreach ($this->sources as $source) {
            foreach (array_keys($source->all()) as $name) {
                if (in_array($name, $names)) {
                    continue;
                }

                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Fetch all the environment variables.
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        $data = [];

        foreach ($this->sources as $source) {
            foreach ($source->all() as $key => $value) {
                if (array_key_exists($key, $data)) {
                    continue;
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }
}
