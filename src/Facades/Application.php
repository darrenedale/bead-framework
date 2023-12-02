<?php

namespace Bead\Facades;

use BadMethodCallException;
use Bead\Contracts\ErrorHandler as ErrorHandlerContract;
use Bead\Contracts\Translator as TranslatorContract;
use Bead\Core\Application as CoreApplication;
use Bead\Database\Connection;
use RuntimeException;

/**
 * @method string rootDir()
 * @method mixed config(string $key, mixed $default)
 * @method string title()
 * @method void setTitle(string $title)
 * @method string version()
 * @method void setVersion(string $version)
 * @method string minimumPhpVersion()
 * @method void setMinimumPhpVersion(string $version)
 * @method void bindService(string $service, mixed $instance)
 * @method void replaceService(string $service, mixed $instance)
 * @method bool service(string $service)
 * @method bool has(string $id)
 * @method mixed get(string $id)
 * @method TranslatorContract|null translator(string $id)
 * @method string|null currentLanguage()
 * @method bool isInDebugMode()
 * @method ErrorHandlerContract errorHandler()
 * @method void setErrorHandler(ErrorHandlerContract $handler)
 * @method Connection|null database()
 * @method bool emitEvent(string $vent, mixed ... $args)
 * @method bool connect(string $vent, callable $callback)
 * @method bool disconnect(string $vent, callable $callback)
 */
class Application
{
    /**
     * Forward static calls on the facade to the underlying Application instance.
     *
     * @param string $method The method to forward.
     * @param array $args The method arguments.
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        $app = CoreApplication::instance();

        if (null === $app) {
            throw new RuntimeException("There is no Application instance.");
        }

        if (!method_exists($app, $method)) {
            throw new BadMethodCallException("The method '{$method}' does not exist in the Application class.");
        }

        return [$app, $method,](...$args);
    }
}
