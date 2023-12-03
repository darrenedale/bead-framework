<?php

declare(strict_types=1);

namespace Bead\Environment;

use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Exceptions\EnvironmentException;
use Bead\Facades\Log;

use function array_unshift;
use function Bead\Helpers\Iterable\some;

/**
 * Provide flexible access to environment variables.
 *
 * The variables are read from one or more sources. Typically, you will set up an instance of this with a source that
 * reads the actual environment variables, and add extra sources to override/augment those values with variables from
 * other sources (for example .env files).
 *
 * The Environment service binder sets up an instance that is bound to the Environment contract, and is available from
 * the environment() method for convenience (or by using the Environment facade). The sources for this service are
 * defined in the env config file.
 *
 * The Environment facade can be used to get quick access to this variables in this instance.
 */
class Environment implements EnvironmentContract
{
    /** @var EnvironmentContract[] The environment variable sources. */
    private array $sources = [];

    /**
     * Add a provider to the environment.
     *
     * @param EnvironmentContract $provider The provider to add.
     */
    public function addSource(EnvironmentContract $provider): void
    {
        array_unshift($this->sources, $provider);
    }

    /**
     * Check whether a key is present in one of the environment's providers.
     *
     * @param string $key The key to check.
     *
     * @return bool true if the key exists in one or more providers, false if not.
     */
    public function has(string $key): bool
    {
        return some($this->sources, fn(EnvironmentContract $provider) => $provider->has($key));
    }

    /**
     * Retrieve a value from the environment.
     *
     * The environment providers are queried in the reverse order in which they were added - more recently added
     * providers override earlier ones.
     *
     * @param string $key The key to fetch.
     *
     * @return string The environment value for the key, or an empty string if no provider contains the key.
     */
    public function get(string $key): string
    {
        foreach ($this->sources as $source) {
            try {
                if ($source->has($key)) {
                    return $source->get($key);
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
            foreach ($source->all() as $name => $value) {
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
