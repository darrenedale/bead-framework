<?php

declare(strict_types=1);

namespace Bead\Facades;

use Bead\Contracts\Environment as EnvironmentContract;

/**
 * Facade for easy access to the running application's environment.
 *
 * @mixin EnvironmentContract
 * @psalm-seal-methods
 * @method static bool has(string $key)
 * @method static string get(string $key)
 * @method static string[] names()
 * @method static array<string,string> all()
 */
final class Environment extends ApplicationServiceFacade
{
    protected static string $serviceInterface = EnvironmentContract::class;
}
