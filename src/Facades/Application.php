<?php

namespace Bead\Facades;

use Bead\Contracts\ErrorHandler as ErrorHandlerContract;
use Bead\Contracts\Translator as TranslatorContract;
use Bead\Core\Application as CoreApplication;
use Bead\Database\Connection;
use LogicException;

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
 * @method static mixed service(string $service)
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
     */
    public static function __callStatic(string $method, array $args)
    {
        $app = CoreApplication::instance();
        assert($app instanceof CoreApplication, new LogicException("There is no Application instance."));
        return [$app, $method,](...$args);
    }
}
