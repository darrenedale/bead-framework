<?php

namespace Bead\Core;

use Bead\Contracts\Binder;
use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Contracts\ErrorHandler;
use Bead\Contracts\ServiceContainer;
use Bead\Contracts\Translator as TranslatorContract;
use Bead\Core\ErrorHandler as BeadErrorHandler;
use Bead\Database\Connection;
use Bead\Environment\Environment;
use Bead\Environment\Sources\Environment as EnvironmentSource;
use Bead\Environment\Sources\File;
use Bead\Exceptions\EnvironmentException;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Exceptions\ServiceNotFoundException;
use Bead\Facades\Log;
use DirectoryIterator;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SplFileInfo;
use Throwable;

use function gettype;

/**
 * Abstract base class for all applications.
 *
 * This provides a bunch of core data and functionality: loading configuration, storing the singleton, the database and
 * various metadata about the application. What you most likely want to do is use or create a subclass of
 * ConsoleApplication or WebApplication.
 *
 * Instances of this class implement PSR11 ContainerInterface.
 */
abstract class Application implements ServiceContainer, ContainerInterface
{
    /** @var int Exit status code for exec() indicating all was well. */
    public const ExitOk = 0;

    /** The singleton instance. */
    protected static ?Application $s_instance = null;

    /** @var string The application's root directory. */
    private string $m_appRoot;

    /** @var string Optional application version string. */
    protected string $m_version = "";

    /** event callback storage. */
    protected array $m_eventCallbacks = [];

    /** @var string Optional application title. */
    protected string $m_title = "";

    /** The minimum PHP version the app requires to run. */
    private string $m_minimumPhpVersion = "0.0.0";

    /** @var ErrorHandler|null The currently installed error handler. */
    private ?ErrorHandler $m_errorHandler = null;

    /** @var array The loaded config. */
    private array $m_config = [];

    /** @var array The sesrvices bound to the container. */
    private array $m_services = [];

    /**
     * @param string $appRoot
     *
     * @throws RuntimeException if the singleton already exists or if the provided root directory does not exist.
     * @throws ServiceAlreadyBoundException if any of the service bindings set up by the Application is already bound..
     */
    public function __construct(string $appRoot)
    {
        $this->setErrorHandler(new BeadErrorHandler());

        if (isset(self::$s_instance)) {
            throw new RuntimeException("Application instance already created.");
        }

        self::$s_instance = $this;
        $realAppRoot = (new SplFileInfo($appRoot))->getRealPath();

        if (false === $realAppRoot) {
            throw new RuntimeException("Application root directory '{$appRoot}' does not exist.");
        }

        $this->m_appRoot = $realAppRoot;
        $this->initialiseEnvironment();
        $this->loadConfig("{$this->m_appRoot}/config");
        $this->bindServices();
    }

    /**
     * Fetch the single instance of the Bead\Application class.
     *
     * The instance is only returned if it is an instance of the class on which the method was invoked - for example, if
     * WebApplication::instance() is called but the instance was constructed as an Application object, null will be
     * returned. This means clients can be confident that they are getting an instance of the expeted class and won't
     * inadvertently call derived-class methods on base class objects.
     *
     * NOTE when we can depend on a PHP8 platform, the return type should change to static.
     *
     * @return Application|null The instance.
     */
    public static function instance(): ?self
    {
        return (self::$s_instance instanceof static) ? self::$s_instance : null;
    }

    /**
     * Initialise the core app environment so that Env is available as early as possible.
     *
     * The default implementation sets up an environment that has the platform environment variables plus the variables
     * defined in .env in the application root. Reimplement this method if you want to do something different. The
     * Environment binder, if/when run, wil **add** to this environment the sources defined in the env.php config file.
     *
     * @return void
     * @throws ServiceAlreadyBoundException
     * @throws EnvironmentException if the .env file in the app root is not valid
     */
    protected function initialiseEnvironment(): void
    {
        $env = new Environment();
        $env->addSource(new EnvironmentSource());

        if (file_exists("{$this->rootDir()}/.env")) {
            $env->addSource(new File("{$this->rootDir()}/.env"));
        }

        $this->bindService(EnvironmentContract::class, $env);
    }

    /** @throws ServiceAlreadyBoundException */
    private function bindServices(): void
    {
        $binders = $this->config("app.binders");

        if (!is_array($binders)) {
            return;
        }

        // instantiate all binders before binding their services
        $instances = [];

        foreach ($binders as $binder) {
            if (!class_exists($binder)) {
                throw new InvalidConfigurationException("app.binders", "The binder {$binder} does not exist");
            }

            if (!(is_subclass_of($binder, Binder::class, true))) {
                throw new InvalidConfigurationException("app.binders", "The binder {$binder} does not implement the " . Binder::class . " interface");
            }

            /** @var Binder $instance */
            $instances[] = new $binder();
        }

        foreach ($instances as $binder) {
            $binder->bindServices($this);
        }
    }

    /**
     * Load the configuration files from the provided directory.
     *
     * @param string $path The directory from which to load the configuration.
     *
     * @throws RuntimeException If an unreadable or invalid configuration file is found.
     */
    private function loadConfig(string $path): void
    {
        $this->m_config = [];

        foreach (new DirectoryIterator($path) as $configFile) {
            if ($configFile->isDot()) {
                continue;
            }

            if ($configFile->isLink() || !$configFile->isFile() || !$configFile->isReadable() || "php" !== $configFile->getExtension()) {
                throw new RuntimeException("config file '{$configFile->getFilename()}' is not valid or is not readable.");
            }

            $this->m_config[$configFile->getBasename(".php")] = include $configFile->getRealPath();
        }
    }

    /**
     * The root directory for the application.
     *
     * @return string
     */
    public function rootDir(): string
    {
        return $this->m_appRoot;
    }

    /**
     * Fetch some config.
     *
     * With no key provided, the whole config is returned. With a key that contains no '.', the config from a single
     * file is returned. With a key that contains a '.' the '.' separates the file from the config item from that file
     * and the item is returned. In all cases the provided default is returned if the config is not foudn.
     *
     * Examples:
     * config() => the whole config
     * config("app") => the whole app config file
     * config("app.title") => the "title" item from the "app" config file
     *
     * @param string|null $key
     *
     * @return array|mixed|null
     */
    public function config(string $key = null, mixed $default = null)
    {
        if (!isset($key)) {
            return $this->m_config;
        }

        if (str_contains($key, ".")) {
            [$file, $key] = explode(".", $key, 2);
            return $this->m_config[$file][$key] ?? $default;
        }

        return $this->m_config[$key] ?? $default;
    }

    /** Fetch the application's title.
     *
     * @return string The title.
     */
    public function title(): string
    {
        return $this->m_title;
    }

    /** Set the application's title.
     *
     * @param $title string The title.
     */
    public function setTitle(string $title): void
    {
        $this->m_title = $title;
    }

    /**
     * Fetch the application's version.
     *
     * @return string The version string.
     */
    public function version(): string
    {
        return $this->m_version;
    }

    /**
     * Set the application's version.
     *
     * @param string $version The version string.
     */
    public function setVersion(string $version): void
    {
        $this->m_version = $version;
    }

    /**
     * Fetch the minimum PHP version the application requires.
     *
     * This will be *0.0.0* by default, effectively meaning any PHP version is acceptable. (Note in reality PHP
     * 7 or later is required by this library.)
     *
     * @return string The minimum PHP version.
     */
    public function minimumPhpVersion(): string
    {
        return $this->m_minimumPhpVersion;
    }

    /**
     * Bind an instance to an identified service.
     *
     * @param string $service The service identifier to bind to.
     * @param mixed $instance The service instance.
     *
     * @throws ServiceAlreadyBoundException if there is already a service bound to the identifier.
     */
    public function bindService(string $service, mixed $instance): void
    {
        if ($this->serviceIsBound($service)) {
            throw new ServiceAlreadyBoundException($service, "The service '{$service}' is already bound to the Application instance.");
        }

        $this->m_services[$service] = $instance;
    }

    /**
     * Replace a service already bound to the Application instance.
     *
     * @param string $service The service identifier to bind to.
     * @param mixed $instance The service instance.
     *
     * @return mixed The previously-bound service.
     * @throws ServiceNotFoundException If no instance is currently bound to the identified service.
     */
    public function replaceService(string $service, mixed $instance): mixed
    {
        if (!$this->serviceIsBound($service)) {
            throw new ServiceNotFoundException($service, "The service '{$service}' is not bound to the Application instance.");
        }

        $previous = $this->m_services[$service];
        $this->m_services[$service] = $instance;
        return $previous;
    }

    /**
     * Check whether a service is bound to an identifier.
     *
     * @param string $service
     *
     * @return bool `true` if the service is bound, `false` if not.
     */
    public function serviceIsBound(string $service): bool
    {
        return array_key_exists($service, $this->m_services);
    }

    /**
     * Fetch the service bound to a given identifier.
     *
     * @param string $service The identifier of the service sought.
     *
     * @return mixed The service.
     * @throws ServiceNotFoundException If no service is bound to the identifier.
     */
    public function service(string $service): mixed
    {
        if (!array_key_exists($service, $this->m_services)) {
            throw new ServiceNotFoundException($service, "The service {$service} was not found in the container.");
        }

        return $this->m_services[$service];
    }

    /**
     * Implemented for PSR11 compatibility.
     *
     * @param string $id The service identifier.
     * @return bool `true` if a service is bound to the given identifier, `false` otherwise.
     */
    public function has(string $id): bool
    {
        return $this->serviceIsBound($id);
    }

    /**
     * Implemented for PSR11 comaptibility.
     *
     * @param string $id The service identifier.
     * @return mixed The service instance.
     * @throws ServiceNotFoundException if no service is bound for the provided identifier.
     */
    public function get(string $id)
    {
        return $this->service($id);
    }

    /**
     * Fetch the application's translator.
     *
     * The application's translator handles translation of strings into the user's chosen language. Client code
     * should never need to use this method: it is far simpler to use the tr() function.
     *
     * @return TranslatorContract|null The translator.
     */
    public function translator(): ?TranslatorContract
    {
        return ($this->serviceIsBound(TranslatorContract::class) ? $this->service(TranslatorContract::class) : null);
    }

    /**
     * Fetch the current language.
     *
     * @return string|null The current language, or @null if no translator is set or the translator has no language
     * set.
     */
    public function currentLanguage(): ?string
    {
        return $this->translator()?->language();
    }

    /**
     * Determine whether the application is set in debug mode.
     *
     * The application is in debug mode if the appropriate setting has been
     * set in the main configuration file (`config/main.phpi`). As a consequence,
     * this method is only reliable if that configuration file has been read
     * before it is called.
     *
     * @return bool _true_ if the application is in debug mode, _false_ otherwise.
     */
    public function isInDebugMode(): bool
    {
        return true === $this->config("app.debugmode", false);
    }

    /**
     * Fetch the current error handler.
     *
     * Applications must always have an installed error handler. The constructor for this base class installs the
     * default Bead error handler. If you find you're receiving a RuntimeException indicating you don't have an error
     * handler, it's likely you've created an Application subclass that doesn't call the base class constructor.
     *
     * @return ErrorHandler
     */
    public function errorHandler(): ErrorHandler
    {
        assert($this->m_errorHandler instanceof ErrorHandler, new RuntimeException("Error handler has been unset."));
        return $this->m_errorHandler;
    }

    /**
     * Set the error handler for the application.
     *
     * @param \Bead\Contracts\ErrorHandler $handler
     *
     * @return void
     */
    public function setErrorHandler(ErrorHandler $handler): void
    {
        $this->m_errorHandler = $handler;

        $errorHandler = function (int $type, string $message, string $file = "", int $line = 0) use ($handler): void {
            $handler->handleError($type, $message, $file, $line);
        };

        set_error_handler($errorHandler);

        set_exception_handler(function (Throwable $err) use ($handler): void {
            $handler->handleException($err);
        });
    }

    /** Fetch the application's data controller.
     *
     * The returned data controller should be used by all classes and plugins whenever access to the database is
     * required.
     *
     * @return Connection|null The data controller.
     */
    public function database(): ?Connection
    {
        return $this->serviceIsBound(Connection::class) ? $this->service(Connection::class) : null;
    }

    /** Emit an event.
     *
     * Any plugin can emit an event, and plugins do not need to (indeed cannot) register their events in advance.
     * Plugins should, however, document the events they emit so that they can be used by other plugins, and are
     * encouraged to name their events clearly and carefully to avoid clashes with events from other plugins,
     * classes, or the application. It is recommended that plugin events are prefixed with the lower-case name of
     * the action that they usually relate to, or if there is no specific action then the text "plugin." followed
     * by the name of the plugin class in lower-case, in order to achieve sufficient disambiguation (e.g.
     * "editpublication.addingpublicationform", "plugin.helloworld.somethinghappened"). This can make for long
     * event
     * names but ensures more compatible plugins.
     *
     * When an event is emitted, all callbacks connected to that event are called. They are currently called in the
     * order in which they are added, but this behaviour should not be relied upon and plugins should expect the
     * order in which callbacks are called to be arbitrary.
     *
     * Events can provide parameters with the event, which will be passed on to all connected callbacks. Plugins
     * must ensure that the quantity, types and meanings of event parameters are used consistently. Any given event
     * must always have the same signature every time it is emitted to keep callbacks as simple to implement as
     * possible. If your plugin needs to emit events with different parameter signatures, use events with different
     * names.
     *
     * @see-also connect(), disconnect()
     *
     * @param $event string The name of the event.
     * @param ...$eventArgs mixed Zero or more arguments to provide to the event callbacks.
     *
     * @return bool _true_ if the event was emitted successfully, _false_ otherwise. An event that is valid but
     * happens to have no connected callbacks returns _true_.
     */
    public function emitEvent(string $event, mixed ... $eventArgs): bool
    {
        $event = strtolower($event);

        if (isset($this->m_eventCallbacks[$event])) {
            foreach ($this->m_eventCallbacks[$event] as $callback) {
                if (!is_callable($callback)) {
                    Log::warning("ignoring un-callable event callback: " . print_r($callback, true));
                    continue;
                }

                $callback($event, ... $eventArgs);
            }
        }

        return true;
    }

    /**
     * Set the minimum PHP version the application requires.
     *
     * The version string should be of the form _x.y.z_ where _x_, _y_ and _z_ are integers >= 0.
     *
     * @param $v string The minimum required PHP version.
     *
     * @return void
     */
    public function setMinimumPhpVersion(string $v): void
    {
        $this->m_minimumPhpVersion = $v;
    }

    /**
     * Connect a callback to an event.
     *
     * ### Warning
     * Connections to events do not account for the fact that PHP method and function names are not case-sensitive.
     * When finding the connections to disconnect, the callback search is \b case-sensitive. To work around this
     * problem you should:
     * - normalise all your callback method and function names for case; or
     * - ensure that you rigorously always use identical strings for method and
     *   function names passed to connect() and disconnect(); or
     * - use references to closures.
     *
     * The callback's syntactic validity is checked when it is connected but not its availability - availability is
     * only checked when the event actually occurs. If the callback is found not to be available when the event
     * occurs, it will silently be ignored.
     *
     * All callbacks are given two parameters before any parameters that are defined by the event provider. The
     * first is the event that occurred (a `string`) and the second is the request that gave rise to the event (a
     * Bead\Request object). In the case of some application events and possibly some plugin events, the
     * Bead\Request can be _null_ (it is up to the plugin to provide the appropriate request when it emits the
     * event, it is not automatically provided by emitEvent()). Plugins that emit events are very strongly
     * recommended to provide a Bead\Request object wherever possible.
     *
     * Callbacks stack up, so if you add the same callback more than once, it will be called more than once every
     * time the event occurs.
     *
     * @param $event string is the event to connect to.
     * @param $callback callable is the function or method to call when the event occurs.
     *
     * @return bool _true_ if the callback was connected to the event, _false_ otherwise.
     */
    public function connect(string $event, callable $callback): bool
    {
        $event = strtolower($event);

        if (!isset($this->m_eventCallbacks[$event])) {
            $this->m_eventCallbacks[$event] = [];
        }

        $this->m_eventCallbacks[$event][] = $callback;
        return true;
    }

    /**
     * Disconnect a callback from an event.
     *
     * If the callback has been connected to the event multiple times, _all_ connections to the event will be
     * disconnected for that callback.
     *
     * Attempting to disconnect a callback that is not connected to the event is not considered an error and is
     * silently ignored.
     *
     * ## Warning
     * Connections to events do not account for the fact that PHP method and function names are not case-sensitive.
     * When finding the connections to disconnect, the callback search is *case-sensitive*. To work around this
     * problem you should:
     * - normalise all your callback method and function names for case; or
     * - ensure that you rigorously always use identical strings for method and function names passed to
     * _connect()_ and
     *   _disconnect()_; or
     * - use references to closures.
     *
     * ### Note
     * Disconnecting a closure for which you have not kept a reference is not possible. Only use lambda literals
     * with connect() if you are certain you will never need to disconnect the function. Disconnecting a reference
     * to a closure previously passed to connect() will work. So do this if you think you might need to disconnect
     * it:
     *
     *     $fn = function() { ... do something ... };
     *     Application::instance()->connect('some.event', $fn);
     *     ...
     *     Application::instance()->disconnect('some.event', $fn);
     *
     * Otherwise you can just do this if you know you will never need to disconnect it:
     *
     *     Application::instance()->connect('some.event', function() { ... do something ... });
     *
     * @param $event string is the event to disconnect from.
     * @param $callback callable is the callback to disconnect.
     *
     * @return bool _true_ if the callback was disconnected (or was not connected in the first place), _false_ if
     * an error occurred.
     */
    public function disconnect(string $event, callable $callback): bool
    {
        $event = strtolower($event);

        if (isset($this->m_eventCallbacks[$event])) {
            $myCallbacks = [];

            foreach ($this->m_eventCallbacks[$event] as $myCallback) {
                if ($callback == $myCallback) {
                    continue;
                }

                $myCallbacks[] = $myCallback;
            }

            $this->m_eventCallbacks[$event] = $myCallbacks;
        }

        return true;
    }

    abstract public function exec(): int;
}
