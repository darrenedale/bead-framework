<?php

namespace Bead\Facades;

use BadMethodCallException;
use Bead\Contracts\Response as ResponseContract;
use Bead\Contracts\Router as RouterContract;
use Bead\Core\Plugin;
use Bead\Web\Application as BeadWebApplication;
use Bead\Web\Request;
use RuntimeException;

use function assert;
use function method_exists;

/**
 * @mixin BeadWebApplication
 * @psalm-seal-methods
 *
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
    /**
     * Forward static calls on the facade to the underlying Web\Application instance.
     *
     * @param string $method The method to forward.
     * @param array $args The method arguments.
     *
     * @throws RuntimeException if there is no Web\Application instance
     */
    public static function __callStatic(string $method, array $args)
    {
        $app = BeadWebApplication::instance();

        if (null === $app) {
            throw new RuntimeException("There is no Bead\Web\Application instance.");
        }

        assert(method_exists($app, $method), new BadMethodCallException("The method '{$method}' does not exist in the Application class."));
        return [$app, $method,](...$args);
    }
}
