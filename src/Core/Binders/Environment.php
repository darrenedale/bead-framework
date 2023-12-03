<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder;
use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Core\Application;
use Bead\Environment\Environment as BeadEnvironment;
use Bead\Environment\Sources\Environment as EnvironmentSource;
use Bead\Environment\Sources\File;
use Bead\Environment\Sources\StaticArray;
use Bead\Exceptions\EnvironmentException;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;

use function array_key_exists;
use function Bead\Helpers\Iterable\all;
use function get_class;
use function gettype;
use function is_array;
use function is_string;
use function realpath;
use function str_starts_with;

/**
 * Bind an Environment instance into the Application based on configuration read from the env config file.
 */
class Environment implements Binder
{
    /**
     * Read the sources that are configured for use in the env config file.
     *
     * @return string[]
     * @throws InvalidConfigurationException
     */
    protected function environmentSources(Application $app): array
    {
        $sources = $app->config("env.environments");

        if (null === $sources) {
            return [];
        }

        if (!all($sources, "is_string")) {
            throw new InvalidConfigurationException("env.environments", "Expecting an array of environments, found a non-string entry");
        }

        return $sources;
    }

    /**
     * Fetch the config for a defined environment source.
     *
     * @param string $source The name of the configured source.
     * @param Application $app
     * @return array THe configuration for the source.
     * @throws InvalidConfigurationException
     */
    protected function sourceConfig(string $source, Application $app): array
    {
        static $config = null;

        if (null === $config) {
            $config = $app->config("env.sources");
        }

        if (!is_array($config)) {
            throw new InvalidConfigurationException("env.sources", "Expected configuration for environment sources to be array, found " . get_class($config));
        }

        if (!array_key_exists($source, $config)) {
            throw new InvalidConfigurationException("env.sources", "Expected configuration for source {$source}, none found");
        }

        if (!is_array($config[$source])) {
            throw new InvalidConfigurationException("env.sources", "Expected configuration for source {$source} to be array, found " . gettype($config[$source]));
        }

        return $config[$source];
    }

    /**
     * Create a File environment source.
     *
     * @param array $config The config for the source from the env config file.
     * @param Application $app
     * @return File
     * @throws InvalidConfigurationException
     */
    final protected function createFileSource(array $config, Application $app): File
    {
        if (!array_key_exists("path", $config)) {
            throw new InvalidConfigurationException("env.sources", "Expecting valid path for File environment source, found none");
        }

        if (!is_string($config["path"])) {
            throw new InvalidConfigurationException("env.sources", "Expecting valid path for File environment source, found " . gettype($config["path"]));
        }

        $path = realpath($app->rootDir() . "/{$config["path"]}");

        if (false === $path) {
            throw new InvalidConfigurationException("env.sources", "Expecting valid path for File environment source, found '{$config["path"]}'");
        }

        if (!str_starts_with($path, $app->rootDir())) {
            throw new InvalidConfigurationException("env.sources", "Expecting path inside application root directory, found '{$config["path"]}'");
        }

        try {
            return new File($path);
        } catch (EnvironmentException $err) {
            throw new InvalidConfigurationException("Exception parsing environment file \"{$path}\": {$err->getMessage()}", previous: $err);
        }
    }

    /**
     * Create a StaticArray environment source.
     *
     * @param array $config The config for the source from the env config file.
     * @param Application $app
     * @return StaticArray
     * @throws InvalidConfigurationException
     */
    final protected function createArraySource(array $config, Application $app): StaticArray
    {
        if (!array_key_exists("env", $config)) {
            throw new InvalidConfigurationException("env.sources", "Expecting array of environment variables for StaticArray environment source, found none");
        }

        if (!is_array($config["env"])) {
            throw new InvalidConfigurationException("env.sources", "Expecting array of environment variables for StaticArray environment source, found " . gettype($config["env"]));
        }

        try {
            return new StaticArray($config["env"]);
        } catch (EnvironmentException $err) {
            throw new InvalidConfigurationException("env.sources", "Exception creating array environment source: {$err->getMessage()}", previous: $err);
        }
    }

    /**
     * Create a source from an entry in the env config file.
     *
     * @param array $config The config for the source.
     * @param Application $app
     * @return EnvironmentContract
     * @throws InvalidConfigurationException
     */
    protected function createSource(array $config, Application $app): EnvironmentContract
    {
        if (!array_key_exists("driver", $config)) {
            throw new InvalidConfigurationException("env.sources", "Expecting valid environment source driver, none found");
        }

        if (!is_string($config["driver"])) {
            throw new InvalidConfigurationException("env.sources", "Expecting valid environment source driver, found " . gettype($config["driver"]));
        }

        return match ($config["driver"]) {
            "file" => $this->createFileSource($config, $app),
            "array" => $this->createArraySource($config, $app),
            "environment" => new EnvironmentSource(),
            default => throw new InvalidConfigurationException("env.sources", "Expecting valid environment source driver, found {$config["driver"]}")
        };
    }

    /**
     * Create the Environment service from the env config.
     *
     * @param Application $app
     * @return BeadEnvironment
     * @throws InvalidConfigurationException
     */
    protected function createEnvironment(Application $app): BeadEnvironment
    {
        $env = new BeadEnvironment();

        foreach ($this->environmentSources($app) as $source) {
            $env->addSource($this->createSource($this->sourceConfig($source, $app), $app));
        }

        return $env;
    }

    /**
     * Bind the service to the Environment contract.
     * @throws InvalidConfigurationException
     * @throws ServiceAlreadyBoundException
     */
    public function bindServices(Application $app): void
    {
        $app->bindService(EnvironmentContract::class, $this->createEnvironment($app));
    }
}
