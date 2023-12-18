<?php
declare(strict_types=1);

namespace Bead\Facades;

use Bead\Contracts\Hasher;
use Bead\Facades\ApplicationServiceFacade;

class Hash extends ApplicationServiceFacade
{
    protected static string $serviceInterface = Hasher::class;
}
