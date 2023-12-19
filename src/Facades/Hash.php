<?php
declare(strict_types=1);

namespace Bead\Facades;

use Bead\Contracts\Hasher;
use Bead\Facades\ApplicationServiceFacade;

/**
 * @method string hash(string $value)
 * @method string verify(string $value, string $hash)
 */
class Hash extends ApplicationServiceFacade
{
    protected static string $serviceInterface = Hasher::class;
}
