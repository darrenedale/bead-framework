<?php

namespace Bead\Facades;

use Bead\Application;
use LogicException;

abstract class ApplicationServiceFacade
{
    /** @var string The interface to which the service that provides the Facade is bound. */
    protected static string $serviceInterface;

    /**
     * Invoke a facade method.
     *
     * The method call is forwarded to the service bound to the facade's interface in the application.
     *
     * @param string $method The method to call.
     * @param array $args The method arguments.
     *
     * @return mixed The value returned by the method call.
     * @throws \Bead\Exceptions\ServiceNotFoundException if no instance is bound to the named interface in the
     * application.
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $app = Application::instance();
        assert($app instanceof Application, new LogicException(static::class . " facade used without Application container instance."));
        $instance = $app->get(static::$serviceInterface);
        assert($instance instanceof static::$serviceInterface, new LogicException("No service bound to " . static::$serviceInterface . " interface."));
        return $instance->{$method}(...$args);
    }
}
