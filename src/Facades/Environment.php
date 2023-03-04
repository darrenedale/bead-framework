<?php

declare(strict_types=1);

namespace Bead\Facades;

use Bead\Application;
use Bead\Contracts\Environment as EnvironmentContract;
use LogicException;

/**
 * Facade for easy access to the running application's environment.
 *
 * @method static bool has(string $key)
 * @method static string get(string $key)
 */
class Environment
{
    public static function __callStatic(string $method, array $args)
    {
        $environment = Application::instance()?->get(EnvironmentContract::class);

        if (!isset($environment)) {
            throw new LogicException("Application environment has not been set up.");
        }

        if (!method_exists($environment, $method)) {
            throw new BadMethodCallException("The method '{$method}' does not exist on the instance bound to " . Environment::class . ".");
        }

        return $environment->{$method}(...$args);
    }
}
