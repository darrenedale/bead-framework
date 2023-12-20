<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder as BinderContract;
use Bead\Contracts\Hasher as HasherContract;
use Bead\Core\Application;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Hashers\ArgonHasher;
use Bead\Hashers\BcryptHasher;

class Hasher implements BinderContract
{
    /**
     * Create the Hasher instance to bind to the contract.
     *
     * @param array $config The hash configuration.
     *
     * @return HasherContract
     * @throws InvalidConfigurationException
     */
    protected static function createHasher(array $config): HasherContract
    {
        return match ($config["driver"]) {
            "bcrypt" => new BcryptHasher($config["cost"] ?? BcryptHasher::DefaultCost),
            "argon" => new ArgonHasher($config["memory_cost"] ?? ArgonHasher::DefaultMemoryCost, $config["time_cost"] ?? ArgonHasher::DefaultTimeCost),
            default => throw new InvalidConfigurationException("hash.driver", "Expected valid hash driver, found {$config["driver"]}"),
        };
    }

    /**
     * Read the hash config and bind the configured hasher to the contract in the application service container.
     *
     * @param Application $app
     *
     * @throws ServiceAlreadyBoundException
     * @throws InvalidConfigurationException
     */
    public function bindServices(Application $app): void
    {
        $hashConfig = $app->config("hash");

        if (!isset($hashConfig["driver"])) {
            return;
        }

        $app->bindService(HasherContract::class, $this->createHasher($hashConfig));
    }
}
