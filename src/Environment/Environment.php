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
 * The variables are read from one or more providers. Typically you will set up an instance of this with a provider that
 * reads the actual environment variables, and add extra providers to override/augment those values with variables from
 * other sources (for example .env files).
 *
 * The Application constructor sets up an instance that is available from the environment() method. By default this
 * reads the actual environment and the .env file in the root directory of the application, if present.
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
}
