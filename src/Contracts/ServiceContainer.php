<?php

namespace Bead\Contracts;

use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Exceptions\ServiceNotFoundException;

/** Contract for objects providing a container for services. */
interface ServiceContainer
{
    /**
     * @template T
     * Bind an instance to an identified service.
     *
     * @param class-string<T>|string $service The service identifier to bind to.
     * @param T|mixed $instance The service instance.
     *
     * @throws ServiceAlreadyBoundException if there is already a service bound to the identifier.
     */
    public function bindService(string $service, mixed $instance): void;

    /**
     * @template T
     * Replace a service already bound to the Application instance.
     *
     * @param class-string<T>|string $service The service identifier to bind to.
     * @param T|mixed $instance The service instance.
     *
     * @return T|mixed The previously-bound service.
     * @throws ServiceNotFoundException If no instance is currently bound to the identified service.
     */
    public function replaceService(string $service, mixed $instance): mixed;

    /**
     * Check whether a service is bound to an identifier.
     *
     * @param string $service The identifier of the service to check.
     *
     * @return bool `true` if the service is bound, `false` if not.
     */
    public function serviceIsBound(string $service): bool;

    /**
     * @template T
     * Fetch the service bound to a given interface/identifier.
     *
     * @param class-string<T>|string $service The identifier of the service sought.
     *
     * @return T|mixed The service.
     * @throws ServiceNotFoundException If no service is bound to the identifier.
     */
    public function service(string $service): mixed;
}
