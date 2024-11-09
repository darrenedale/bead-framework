<?php

namespace Bead\Facades;

use Bead\Contracts\Web\Response as ResponseContract;
use Bead\Contracts\Web\Router as RouterContract;
use Bead\Core\Plugin;
use Bead\Web\Application as BeadWebApplication;
use Bead\Web\Request;
use LogicException;

use function assert;

/**
 * Facade for easy access to the Web\Application instance (if it exists).
 *
 * @mixin BeadWebApplication
 * @psalm-seal-methods
 * @method static bool isRunning()
 * @method static string routesDirectory()
 * @method static string pluginsDirectory()
 * @method static string pluginsNamespace()
 * @method static string[] loadedPlugins()
 * @method static Plugin|null pluginByName(string $name)
 * @method static void setRouter(RouterContract $router)
 * @method static RouterContract router()
 * @method static void sendResponse(ResponseContract $response)
 * @method static Request request()
 * @method static string csrf()
 * @method static string regenerateCsrf()
 * @method static ResponseContract handleRequest(Request $request)
 * @method static int exec()
 */
class WebApplication extends Application
{
    /**
     * Forward static calls on the facade to the underlying Web\Application instance.
     *
     * @param string $method The method to forward.
     * @param array $args The method arguments.
     */
    public static function __callStatic(string $method, array $args)
    {
        $app = BeadWebApplication::instance();
        assert($app instanceof BeadWebApplication, new LogicException("There is no Web\Application instance"));
        return [$app, $method,](...$args);
    }
}
