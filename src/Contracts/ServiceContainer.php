<?php

namespace Bead\Contracts;

use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Exceptions\ServiceNotFoundException;

interface ServiceContainer
{
    /**
     * Bind an instance to an identified service.
     *
     * @param string $service The service identifier to bind to.
     * @param mixed $instance The service instance.
     *
     * @throws ServiceAlreadyBoundException if there is already a service bound to the identifier.
     */
    public function bindService(string $service, $instance): void;

    /**
     * Replace a service already bound to the Application instance.
     *
     * @param string $service The service identifier to bind to.
     * @param mixed $instance The service instance.
     *
     * @return mixed The previously-bound service.
     * @throws ServiceNotFoundException If no instance is currently bound to the identified service.
     */
    public function replaceService(string $service, $instance);

    /**
     * Check whether a service is bound to an identifier.
     *
     * @param string $service The identifier of the service to check.
     *
     * @return bool `true` if the service is bound, `false` if not.
     */
    public function serviceIsBound(string $service): bool;

    /**
     * Fetch the service bound to a given identifier.
     *
     * @param string $service The identifier of the service sought.
     *
     * @return mixed The service.
     * @throws ServiceNotFoundException If no service is bound to the identifier.
     */
    public function service(string $service);
}
