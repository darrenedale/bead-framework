<?php

declare(strict_types=1);

namespace Bead\Facades;

use BadMethodCallException;
use Bead\Application;
use Bead\Contracts\Environment as EnvironmentContract;
use LogicException;

/**
 * Facade for easy access to the running application's environment.
 *
 * @method static bool has(string $key)
 * @method static string get(string $key)
 */
final class Environment extends ApplicationServiceFacade
{
    protected static string $serviceInterface = EnvironmentContract::class;
}
