<?php

namespace Bead\Facades;


use BadMethodCallException;
use Bead\Contracts\Response as ResponseContract;
use Bead\Contracts\Router as RouterContract;
use Bead\Core\Plugin;
use Bead\Web\Application as BeadWebApplication;
use Bead\Web\Request;
use RuntimeException;

/**
 * @method bool isRunning()
 * @method string routesDirectory()
 * @method string pluginsDirectory()
 * @method string pluginsNamespace()
 * @method string[] loadedPlugins()
 * @method Plugin|null pluginByName(string $name)
 * @method void setRouter(RouterContract $router)
 * @method RouterContract router()
 * @method void sendResponse(ResponseContract $response)
 * @method Request request()
 * @method string csrf()
 * @method string regenerateCsrf()
 * @method ResponseContract handleRequest(Request $request)
 * @method int exec()
 */
class WebApplication extends Application
{
    public static function __callStatic(string $method, array $args)
    {
        $app = BeadWebApplication::instance();

        if (null === $app) {
            throw new RuntimeException("There is no Bead\Web\Application instance.");
        }

        if (!method_exists($app, $method)) {
            throw new BadMethodCallException("The method '{$method}' does not exist in the Bead\Web\Application class.");
        }

        return [$app, $method,](...$args);
    }
}
