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
     */
    public static function __callStatic(string $method, array $args)
    {
        $app = BeadWebApplication::instance();
        assert($app instanceof BeadWebApplication, new RuntimeException("There is no Application instance."));
        assert(method_exists($app, $method), new BadMethodCallException("The method '{$method}' does not exist in the Application class."));
        return [$app, $method,](...$args);
    }
}
