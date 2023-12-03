<?php

declare(strict_types=1);

namespace BeadTests\Facades;

use Bead\Facades\ApplicationServiceFacade;

/**
 * @method static string doSomething()
 */
final class TestApplicationServiceFacade extends ApplicationServiceFacade
{
    protected static string $serviceInterface = TestApplicationService::class;
}
