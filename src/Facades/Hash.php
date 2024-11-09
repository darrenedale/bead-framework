<?php

declare(strict_types=1);

namespace Bead\Facades;

use Bead\Contracts\Hasher as HasherContract;

/**
 * Facade for easy access to the Application container's hasher.
 *
 * @mixin HasherContract
 * @psalm-seal-methods
 * @method static string hash(string $value)
 * @method static string verify(string $value, string $hash)
 */
class Hash extends ApplicationServiceFacade
{
    protected static string $serviceInterface = HasherContract::class;
}
