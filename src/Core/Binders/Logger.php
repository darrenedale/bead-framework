<?php
declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder;
use Bead\Contracts\ServiceContainer;
use Bead\Core\Application;

/**
 * Bind logging services into the application service container.
 */
class Logger implements Binder
{
     /**
     *  Bind services into the container.
     *
     * @param Application $app The application service container into which to bind services.
     */
    public function bindServices(ServiceContainer $app): void
    {
        // TODO: Implement bindServices() method.
    }
}