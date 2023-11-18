<?php
declare(strict_types=1);

namespace Bead\Contracts;

use Bead\Core\Application;

/**
 * Contract for classes that bind services into the service container.
 */
interface Binder
{
    /**
     * Bind services into the application service container.
     *
     * @param Application $app The application service container to bind into.
     */
    public function bindServices(Application $app): void;
}
