<?php

namespace Bead\Facades;

use BadMethodCallException;
use Bead\Contracts\ErrorHandler as ErrorHandlerContract;
use Bead\Contracts\Translator as TranslatorContract;
use Bead\Core\Application as CoreApplication;
use Bead\Database\Connection;
use RuntimeException;

/**
 * @mixin CoreApplication
 * @psalm-seal-methods
 *
 * @method static string rootDir()
 * @method static mixed config(string $key, mixed $default = null)
 * @method static string title()
 * @method static void setTitle(string $title)
 * @method static string version()
 * @method static void setVersion(string $version)
 * @method static string minimumPhpVersion()
 * @method static void setMinimumPhpVersion(string $version)
 * @method static void bindService(string $service, mixed $instance)
 * @method static void replaceService(string $service, mixed $instance)
 * @method static bool service(string $service)
 * @method static bool has(string $id)
 * @method static mixed get(string $id)
 * @method static TranslatorContract|null translator(string $id)
 * @method static string|null currentLanguage()
 * @method static bool isInDebugMode()
 * @method static ErrorHandlerContract errorHandler()
 * @method static void setErrorHandler(ErrorHandlerContract $handler)
 * @method static Connection|null database()
 * @method static bool emitEvent(string $vent, mixed ... $args)
 * @method static bool connect(string $vent, callable $callback)
 * @method static bool disconnect(string $vent, callable $callback)
 */
class Application
{
    /**
     * Forward static calls on the facade to the underlying Application instance.
     *
     * @param string $method The method to forward.
     * @param array $args The method arguments.
     *
     * @throws RuntimeException if there is no Application instance
     */
    public static function __callStatic(string $method, array $args)
    {
        $app = CoreApplication::instance();

        if (null === $app) {
            throw new RuntimeException("There is no Application instance.");
        }

        assert(method_exists($app, $method), new BadMethodCallException("The method '{$method}' does not exist in the Application class."));
        return [$app, $method,](...$args);
    }
}
