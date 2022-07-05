<?php

namespace Equit;

use DirectoryIterator;
use Equit\Contracts\Response;
use Equit\Contracts\Router as RouterContract;
use Equit\Database\Connection;
use Equit\Exceptions\CsrfTokenVerificationException;
use Equit\Exceptions\InvalidPluginException;
use Equit\Exceptions\InvalidPluginsDirectoryException;
use Equit\Exceptions\InvalidRoutesDirectoryException;
use Equit\Exceptions\InvalidRoutesFileException;
use Equit\Exceptions\NotFoundException;
use Equit\Exceptions\UnroutableRequestException;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Core Application class for sites/applications using the framework.
 *
 * ## Introduction
 * Despite its name, this class doesn't actually implement an application. What it does is provide application-level
 * services and acts as a request dispatcher. An instance of the `WebApplication` class is the core of any application
 * that uses the framework to run a website. Only a single `WebApplication` instance may be created by any application.
 * Applications using the framework create an instance of (a subclass of) the `WebApplication` class, call its `exec()`
 * method and wait for it to return.
 *
 * When the `exec()` method is called it reads the received HTTP request, passes it to `handleRequest()` for dispatch,
 * and returns. `handleRequest()` asks the installed `Router` to route the request to a handler, which actions the
 * request and returns the `Response`. The response is then sent to the client and `exec()` returns. The
 * `WebApplication` instance emits some useful events along the way.
 *
 * Some useful administrative information about the running application can be set using the `setTitle()`,
 * `setVersion()` and `setMinimumPhpVersion()` methods. The title and version are made available to any other code that
 * fetches the running instance so that, for example, user messages can show the application title without having to
 * have it hard-coded.
 *
 * Applications that require a particular minimum version of PHP can set it using the `setMinimumPhpVersion()`
 * method. If this is done before `exec()` is called, the application will exit with an appropriate error message
 * when `exec()` is called if the PHP version on which the application is running is below the minimum set.
 *
 * For applications that make use of a data store, the `database()` method provides access to the `Connection`
 * responsible for the interface between the application and the data store.
 *
 * The running `WebApplication` instance can be retrieved using the `instance()` static method. This instance provides
 * access to all the services that the application provides.
 *
 * ## Plugins
 * Plugins are loaded automatically by the `exec()` method and are sourced from the `app/plugins/` subdirectory by
 * default. This can be customised in the app config file, by providing a path (relative to the application's root
 * directory) in the `plugins.path` item. Any plugin found in this directory is loaded. Plugin classes should be in the
 * `App\Plugins` namespace. This too can be customised in the app config file, by providing a valid namespace in the
 * `plugins.namespace` item. All plugins must be in the same namespace.
 *
 * Subdirectories within the plugins directory are not scanned. It is therefore sufficient to install a plugin's PHP
 * file in the plugins subdirectory for it to be loaded and enabled by the application.
 *
 * The primary use-case for plugins is to monitor for events and augment the functionality of the application while not
 * actually handling requests themselves.
 *
 * ## Requests
 * The request being handled can always be retrieved using the `request()` method. This retrieves the original HTTP
 * request that was submitted by the user agent (in other words the request `exec()` provided to `handleRequest()`).
 *
 * ## Inter-module communication
 * A simple inter-object communication mechanism is implemented by the Application class. This mechanism is based
 * on the concept of named events being emitted and objects subscribing to those events. Emitted events can
 * provide additional arguments that provide more details of the event (for example an event that fires when a
 * particular type of search has been executed might provide the search terms and result set as additional
 * arguments).
 *
 * Events are emitted by calling the `emitEvent()` method. Subscriptions to events are achieved by calling the
 * `connect()` method. Subscriptions can be unsubscribed by calling `disconnect()`. Events do not need to be
 * registered or defined before they are emitted - it is sufficient just to call `emitEvent()` in order to emit an
 * event. Any code -- plugin, class or even the main application script or `WebApplication` object -- can emit events.
 *
 * Emitters of events should take care to document the events they emit and the arguments that are provided with
 * them, and should strive to keep the signatures of their events stable (API stability) and the names of their
 * events distinct to avoid event naming clashes between different emitters.
 *
 * ## Session management
 * The `WebApplication` class can be used to manage session data in a way that all-but guarantees clashes between the
 * bead app and other applications running on the same domain are avoided. It implements a mechanism that is very simple
 * to use by hiding the complexities of keeping session data distinct behind simple, unique "context" strings. See the
 * `sessionData()` method for details of how this works.
 *
 * ### Events
 * This module emits the following events.
 *
 * - `application.pluginsloaded`
 *   Emitted when the `exec()` method has finished loading all the plugins.
 *
 * - `application.executionstarted`
 *   Emitted when `exec()` starts actual execution (just before it calls `handleRequest()`).
 *
 * - `application.handlerequest.requestreceived($request)`
 *   Emitted when `handleRequest()` receives a request to process.
 *
 *   `$request` `Request` The request that was received.
 *
 * - `application.handlerequest.routing(Request $request)`
 *   Emitted when `handleRequest()` is about to match the incoming Request to a route using the application's router.
 *
 * - `application.handlerequest.routed(Request $request)`
 *   Emitted when `handleRequest()` has successfully matched and routed the incoming `Request` to a route using the
 *   application's router.
 *
 * - `application.executionfinished`
 *   Emitted by `exec()` when `handleRequest()` returns from processing the original HTTP request.
 *
 * - `application.sendingresponse`
 *   Emitted by `exec()` when the response returned by handleRequest() is about to be sent to the client.
 *
 * - `application.responsesent`
 *   Emitted by `exec()` immediately after the response returned by handleRequest() has been sent to the client.
 *
 * ### Session Data
 * The Application class creates a session context with the identifier **application**.
 *
 * @events application.pluginsloaded application.executionstarted application.handlerequest.requestreceived
 *     application.handlerequest.routing application.handlerequest.routed
 *     application.executionfinished application.sendingresponse application.responsesent
 * @session application
 *
 * @method static self instance()
 */
class WebApplication extends Application
{
	/** @var string The context name for this class's session data. */
	public const SessionDataContext = "application";

	/** @var string Where plugins are loaded from by default. Relative to the app root directory. */
	protected const DefaultPluginsPath = "app/Plugins";

	/** @var string The default namespace for plugin classes. */
	protected const DefaultPluginsNamespace = "App\\Plugins";

	/** @var string Where plugins are loaded from. */
	private string $m_pluginsDirectory = self::DefaultPluginsPath;

	/** @var string The namespace where plugins are located. */
	private string $m_pluginsNamespace = self::DefaultPluginsNamespace;

	/** WebApplication class's session data array. */
	protected ?array $m_session = null;

	/** Loaded plugin storage.*/
	private array $m_pluginsByName = [];

	/** @var bool True when exec() is in progress, false otherwise. */
	private bool $m_isRunning = false;

	/** @var RouterContract The router that routes requests to handlers. */
	private RouterContract $m_router;

	/**
	 * Construct a new WebApplication object.
	 *
	 * WebApplication is a singleton class. Once an instance has been created, attempts to create another will throw.
	 *
	 * @param $appRoot string The path to the root of the application. This helps locate files (e.g. config files).
	 * @param $db Connection|null The data controller for the application.
	 *
	 * @throws \Exception if an Application instance has already been created.
	 */
	public function __construct(string $appRoot, ?Connection $db = null)
	{
		parent::__construct($appRoot, $db);
		$this->initialiseSession();
		$this->m_session = &$this->sessionData(self::SessionDataContext);
		$this->setRouter(new Router());

		if (!empty($this->config("app.plugins.path"))) {
			$this->setPluginsDirectory($this->config("app.plugins.path"));
		}

		if (!empty($this->config("app.plugins.namespace"))) {
			$this->setPluginsNamespace($this->config("app.plugins.namespace"));
		}
	}

	/**
	 * Initialise the bead-framework application's session data.
	 */
	private function initialiseSession(): void
	{
		session_start();

		if (!array_key_exists("bead-app", $_SESSION) || !is_array($_SESSION["bead-app"])) {
			$_SESSION["bead-app"] = [];
		}

		// forces the CSRF token to be generated if there isn't one
		$this->csrf();
		$this->sessionData(self::SessionDataContext)["_transient"] = [];
		$this->sessionData(self::SessionDataContext)["_transient.flush"] = [];
	}

	/**
	 * Determine whether the application is currently running or not.
	 *
	 * The application is running if its `exec()` method has been called and has not yet returned.
	 *
	 * @return bool `true` if the application is running, `false` otherwise.
	 */
	public function isRunning(): bool
	{
		return $this->m_isRunning;
	}

	/**
	 * Set the plugins directory.
	 *
	 * The plugins directory can only be set before `exec()` is called. If `exec()` has been called, calling
	 * `setPluginsDirectory()` will fail.
	 *
	 * @param string $dir The directory to load plugins from.
	 *
	 * @return bool `true` If the provided directory was valid and was set, `false` otherwise.
	 */
	public function setPluginsDirectory(string $dir): bool
	{
		if ($this->isRunning()) {
			AppLog::error("can't set plugins path while application is running", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if (!preg_match("|[a-zA-Z0-9_-][/a-zA-Z0-9_-]*|", $dir)) {
			AppLog::error("invalid plugins path: \"$dir\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_pluginsDirectory = $dir;
		return true;
	}

	/**
	 * Fetch the plugins path.
	 *
	 * This is the path from which plugins will be/were loaded.
	 *
	 * @return string The plugins path.
	 */
	public function pluginsDirectory(): string
	{
		return $this->m_pluginsDirectory;
	}

	/**
	 * Fetch the routes directory.
	 *
	 * The directory is relative to the application's root directory. The default is "routes".
	 *
	 * @return string The directory.
	 */
	public function routesDirectory(): string
	{
		return $this->config("app.routes.directory", "routes");
	}

	/**
	 * Set the namespace for plugins.
	 *
	 * @param string $namespace The namespace.
	 */
	public function setPluginsNamespace(string $namespace): void
	{
		$this->m_pluginsNamespace = $namespace;
	}

	/**
	 * Fetch the namespace for plugins.
	 *
	 * @return string The namespace.
	 */
	public function pluginsNamespace(): string
	{
		return $this->m_pluginsNamespace;
	}

	/**
	 * Fetch the application's session data.
	 *
	 * This method should be used to access the session data rather than using `$_SESSION` directly as it ensures
	 * that the bead framework application's session data is kept separate from any other session code's session data.
	 * Use of the context parameter ensures that different parts of the application can keep their session data separate
	 * from other parts and therefore avoid namespace clashes and so on.
	 *
	 * The session data for the context is returned as an array reference, which calling code will need to assign by
	 * reference in order to use successfully. If it is not assigned by reference, any changes made to the provided
	 * session array will not persist between requests. To do this, do something like the following in your code:
	 *
	 *     $session = & Equit\WebApplication::instance()->sessionData("context");
	 *
	 * Once you have done this, you can use `$session` just like you would use `$_SESSION` to store your session data.
	 *
	 * There is nothing special that needs to be done to create a new session context. If a request is made for a
	 * context that does not already exist, a new one is automatically initialised and returned.
	 *
	 * @param $context string A unique context identifier for the session data.
	 *
	 * @return array<mixed,mixed> A reference to the session data for the given context.
	 * @throws InvalidArgumentException If an empty context is given.
	 */
	public function & sessionData(string $context): array
	{
		if (empty($context)) {
			throw new InvalidArgumentException("Session context must not be empty.");
		}

		// ensure context is not numeric (avoids issues when un-serialising session data)
		$context = "ctx-$context";

		if (!isset($_SESSION["bead-app"][$context])) {
			$_SESSION["bead-app"][$context] = [];
		}

		$session = &$_SESSION["bead-app"][$context];
		return $session;
	}

	/**
	 * Store some session data for just the next request.
	 *
	 * The data persists for a given number of extra requests. If the age is 0 or less, the data only persists for the
	 * current request (i.e. it's not all that different from a normal variable). The default is 1 to persist the data
	 * for the next request only.
	 *
	 * @param string $context The session context.
	 * @param string $key The session data key.
	 * @param mixed $value The data.
	 * @param int $age How many requests the data should persist for. Default is 1.
	 */
	public function storeTransientSessionData(string $context, string $key, $value, int $age = 1)
	{
		$this->sessionData($context)[$key] = $value;
		$this->sessionData(self::SessionDataContext)["_transient"]["{$context}::{$key}"] = $age;
	}

	/**
	 * Empty the expired transient session data.
	 */
	protected function flushTransientSessionData(): void
	{
		// destroy the transient data that's been around for more than one request
		foreach ($this->sessionData(self::SessionDataContext)["_transient"] as $key => $age) {
			--$age;

			if (0 >= $age) {
				[$context, $key] = explode("::", $key, 2);
				unset($this->sessionData($context)[$key]);
				unset($this->sessionData(self::SessionDataContext)["_transient"][$key]);
			}
		}
	}

	/**
	 * Set the application's Request router.
	 *
	 * @param RouterContract $router
	 */
	public function setRouter(RouterContract $router): void
	{
		$this->m_router = $router;
	}

	/**
	 * Fetch the application's Request router.
	 *
	 * @return RouterContract The router.
	 */
	public function router(): RouterContract
	{
		return $this->m_router;
	}

	/**
	 * Fetch the expected fully-qualified name for a plugin loaded from a given path.
	 *
	 * The default is to take the basename of the path and append it to the plugins namespace to construct the FQ name.
	 *
	 * @param string $path The path from which the plugin is being loaded.
	 *
	 * @return string The expected fully-qualified class name.
	 */
	protected function pluginClassNameForPath(string $path): string
	{
		$className = basename($path, ".php");
		return "{$this->pluginsNamespace()}\\$className";
	}

	/**
	 * Load a plugin.
	 *
	 * Various checks are performed to ensure that the path represents a genuine plugin for the application. If it does,
	 * it is loaded and added to the application's set of available plugins. Each plugin is guaranteed to be loaded just
	 * once.
	 *
	 * Plugins are required to meet the following conditions:
	 * - defined in a file named exactly as the plugin class is named, with the extension ".php"
	 * - define a class that inherits the `Equit\Plugin` base class
	 * - provide a valid instance of the appropriate class from the `instance()` method of the main plugin class
	 *   defined in the file
	 *
	 * @param $path string The path to the plugin to load.
	 *
	 * @throws InvalidPluginException
	 */
	private function loadPlugin(string $path): void
	{
		if (!is_file($path) || !is_readable($path)) {
			throw new InvalidPluginException($path, null, "Plugin file \"{$path}\" is not a file or is not readable.");
		}

		if (".php" != substr($path, -4)) {
			throw new InvalidPluginException($path, null, "Plugin file \"{$path}\" is not a PHP file.");
		}

		// NOTE this currently requires plugins to be in the global namespace
		$className    = $this->pluginClassNameForPath($path);
		$classNameKey = mb_convert_case($className, MB_CASE_LOWER, "UTF-8");

		if (isset($this->m_pluginsByName[$classNameKey])) {
			return;
		}

		include_once($path);

		if (!class_exists($className)) {
			throw new InvalidPluginException($path, null, "Plugin file \"{$path}\" does not define the expected \"{$className}\" class.");
		}

        $pluginClassInfo = new ReflectionClass($className);

		if (!$pluginClassInfo->isSubclassOf(Plugin::class)) {
			throw new InvalidPluginException($path, null, "Plugin file \"{$path}\" contains the class \"{$className}\" which does not implement " . Plugin::class);
		}

		try {
			$instanceFn = $pluginClassInfo->getMethod("instance");
		} catch (ReflectionException $err) {
			throw new InvalidPluginException($path, null, "Exception introspecting {$className}::instance() method: [{$err->getCode()}] {$err->getMessage()}", 0, $err);
		}

		if (!$instanceFn->isPublic() || !$instanceFn->isStatic()) {
			throw new InvalidPluginException($path, null, "{$className}::instance() method must be public static",);
		}

		if (0 != $instanceFn->getNumberOfRequiredParameters()) {
			throw new InvalidPluginException($path, null, "{$className}::instance() method must be callable with no arguments");
		}

		$instanceFnReturnType = $instanceFn->getReturnType();

		if (!$instanceFnReturnType) {
			throw new InvalidPluginException($path, null, "{$className}::instance() has no return type");
		}

		if ($instanceFnReturnType->isBuiltin() || ("self" != $instanceFnReturnType->getName() && !is_a($instanceFnReturnType->getName(), Plugin::class, true))) {
			throw new InvalidPluginException($path, null, "{$className}::instance() must return an instance of {$className}");
		}

        try {
            $plugin = $instanceFn->invoke(null);
        } catch (ReflectionException $err) {
			throw new InvalidPluginException($path, null, "Exception invoking {$className}::instance(): [{$err->getCode()}] {$err->getMessage()}", 0, $err);
        }

		if (!$plugin instanceof $className) {
			throw new InvalidPluginException($path, null, "{$className}::instance() did not provide an object of the {$className}.");
		}

		$this->m_pluginsByName[$classNameKey] = $plugin;
	}

	/**
	 * Load all the available plugins.
	 *
	 * Plugins are loaded from the configured plugins path. All valid plugins found are loaded and instantiated.
	 *
	 * @return bool true if the plugins path was successfully scanned for plugins, false otherwise.
	 * @throws InvalidPluginsDirectoryException if the plugins path can't be read for some reason.
	 * @throws InvalidPluginException if the plugins path can't be read for some reason.
	 */
	protected function loadPlugins(): bool
	{
		static $s_done = false;

		if (!$s_done) {
			$info = new SplFileInfo("{$this->rootDir()}/{$this->pluginsDirectory()}");

			if (!$info->isDir()) {
				throw new InvalidPluginsDirectoryException($this->pluginsDirectory(), "Plugin directory \"{$this->pluginsDirectory()}\" is not a directory.");
			}

			if (!$info->isReadable() || !$info->isExecutable()) {
				throw new InvalidPluginsDirectoryException($this->pluginsDirectory(), "Plugin directory \"{$this->pluginsDirectory()}\" cannot be scanned for plugins to load.");
			}

			/* load the ordered plugins, then the rest after */
			$pluginLoadOrder = $this->config("app.plugins.loadorder", []);

			foreach ($pluginLoadOrder as $pluginName) {
				$pluginFile = new SplFileInfo("{$info->getRealPath()}/{$pluginName}.php");
				$pluginFilePath = $pluginFile->getRealPath();

				if (false !== $pluginFilePath) {
					$this->loadPlugin($pluginFilePath);
				}
			}

			try {
				$directory = new DirectoryIterator("{$info->getRealPath()}");
			} catch (UnexpectedValueException $err) {
				throw new InvalidPluginsDirectoryException($this->pluginsDirectory(), "Plugin directory \"{$this->pluginsDirectory()}\" cannot be scanned for plugins to load.", 0, $err);
			}

			foreach ($directory as $pluginFile) {
				if ($pluginFile->isDot()) {
					continue;
				}

				$this->loadPlugin($pluginFile->getRealPath());
			}

			$s_done = true;
			$this->emitEvent("application.pluginsloaded");
		}

		return true;
	}

	/**
	 * Load all the routes files in the routes directory.
	 *
	 * @throws InvalidRoutesDirectoryException
	 * @throws InvalidRoutesFileException
	 */
	protected function loadRoutes(): void
	{
		static $s_done = false;

		if (!$s_done) {
			$dir = new SplFileInfo("{$this->rootDir()}/{$this->routesDirectory()}");

			if (!$dir->isDir()) {
				throw new InvalidRoutesDirectoryException($this->pluginsDirectory(), "Routes directory \"{$this->pluginsDirectory()}\" is not a directory.");
			}

			if (!$dir->isReadable() || !$dir->isExecutable()) {
				throw new InvalidRoutesDirectoryException($this->routesDirectory(), "Routes directory \"{$this->routesDirectory()}\" cannot be scanned for route files to load.");
			}

			$app = $this;
			$router = $this->router();

			foreach (new DirectoryIterator($dir) as $routeFile) {
				if ($routeFile->isDot() || !$routeFile->isFile()) {
					continue;
				}

				if ($routeFile->isLink()) {
					throw new InvalidRoutesFileException($routeFile->getRealPath(), "Routes file {$routeFile->getRealPath()} cannot be loaded because linked routes files are not supported for security reasons.");
				}

				$routeFile = $routeFile->getRealPath();

				try {
					// isolate the context of the included routes file
					(function () use ($app, $router, $routeFile) {
						include $routeFile;
					})();
				} catch (Exception $err) {
					throw new InvalidRoutesFileException($routeFile, "The routes file {$routeFile} could not be loaded.", 0, $err);
				}
			}
		}
	}

	/**
	 * Fetch the list of loaded plugins.
	 *
	 * @return array<string> The names of the loaded plugins.
	 */
	public function loadedPlugins(): array
	{
		return array_keys($this->m_pluginsByName);
	}

	/**
	 * Fetch a plugin by its name.
	 *
	 * If the plugin has been loaded, the created instance of that plugin will be returned. The provided class name must
	 * be fully-qualified with its namespace.
	 *
	 * @param $name string The class name of the plugin.
	 *
	 * @return Plugin|null The loaded plugin instance if the named plugin was loaded, `null` otherwise.
	 */
	public function pluginByName(string $name): ?Plugin
	{
		$this->loadPlugins();
		return $this->m_pluginsByName[mb_strtolower($name, "UTF-8")] ?? null;
	}

	/**
	 * Send a response to the client.
	 *
	 * @param Response $response The response to send.
	 */
	public function sendResponse(Response $response): void
	{
		if (0 != ob_get_level() && !ob_end_clean()) {
			throw new RuntimeException("Failed to clear output buffer before sending response.");
		}

		$response->send();
	}

	/** Fetch the request submitted by the user.
	 *
	 * This method fetches the original request received from the user. It is just a convenience synonym for
	 * LibEquit\Request::originalRequest().
	 *
	 * @see-also currentRequest()
	 *
	 * @return Request The user's original request.
	 */
	public function request(): Request
	{
		return Request::originalRequest();
	}

	/**
	 * Fetch the current CSRF token.
	 *
	 * @return string The token.
	 */
	public function csrf(): string
	{
		if (!isset($this->m_session["csrf-token"])) {
			$this->regenerateCsrf();
		}

		return $this->m_session["csrf-token"];
	}

	/**
	 * Force the CSRF token to be regenerated.
	 *
	 * By default a 64-character random string is generated.
	 */
	public function regenerateCsrf(): void
	{
		$this->m_session["csrf-token"] = randomString(64);
	}

	/**
	 * Determine whether the incoming request must pass CSRF verification.
	 *
	 * The default behaviour is to require verification for all requests that don't use the GET, HEAD or OPTIONS HTTP
	 * methods. Use this method as a customisation point in your WebApplication subclass to implement more detailed
	 * logic.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return bool `true` if the request requires CSRF validation, `false` if not.
	 */
	protected function requestRequiresCsrf(Request $request): bool
	{
		switch ($request->method()) {
			case "GET":
			case "HEAD":
			case "OPTIONS":
				return false;
		}

		return true;
	}

	/**
	 * Extract the CSRF token submitted with a request.
	 *
	 * Use this as a customisation point in your WebApplication subclass if you need custom logic to obtain the token
	 * from Requests. The default behaviour is to look for a `_token` POST field, or an X-CSRF-TOKEN header if the
	 * field is not present (the latter case is primarily for AJAX requests).
	 *
	 * @param Request $request The request from which to extract the CSRF token.
	 *
	 * @return string|null The token, or `null` if no CSRF token is found in the request.
	 */
	protected function csrfTokenFromRequest(Request $request): ?string
	{
		return $request->postData("_token") ?? $request->header("X-CSRF-TOKEN");
	}

	/**
	 * Helper to verify the CSRF token in an incoming request is correct, if necessary.
	 *
	 * Not all requests require CSRF verification. requestRequiresCsrf() is used to determine whether the request
	 * requires it. The CSRF token is extracted from the request by csrfTokenFromRequest().
	 *
	 * @param Request $request The incoming request.
	 *
	 * @throws CsrfTokenVerificationException if the CSRF token in the request is not verified.
	 */
	protected function verifyCsrf(Request $request): void
	{
		if (!$this->requestRequiresCsrf($request)) {
			return;
		}

		$requestCsrf = $this->csrfTokenFromRequest($request);

		if (!isset($requestCsrf) || !hash_equals($this->csrf(),  $requestCsrf)) {
			throw new CsrfTokenVerificationException($request, "The CSRF token is missing from the request or is invalid.");
		}
	}

	/**
	 * Handle a request.
	 *
	 * This method submits a request to the application for processing. Processing of the request starts immediately and
	 * any current request is held until it finishes.
	 *
	 * @param $request Request The request to handle.
	 *
	 * @return Response An optional Response to send to the client. For legacy support, if no response is returned
	 * exec() assumes that content has been added to the Page instance and that is output instead.
	 * @throws CsrfTokenVerificationException if the request requires CSRF verification and fails
	 */
	public function handleRequest(Request $request): Response
	{
		$this->emitEvent("application.handlerequest.requestreceived", $request);
		$this->verifyCsrf($request);

		try {
			$this->emitEvent("application.handlerequest.routing", $request);
			$response = $this->router()->route($request);
			$this->emitEvent("application.handlerequest.routed", $request);
		} catch (UnroutableRequestException $err) {
			throw new NotFoundException($request, "", 0, $err);
		}

		return $response;
	}

	/**
	 * Execute the application.
	 *
	 * Start execution of the application. This method will pass the original request from the user to
	 * `handleRequest()` and return when processing of that request completes. This method should never be called,
	 * except from the script that is in use as the application bootstrap.
	 *
	 * This method is responsible for setting up the execution context for the request, including initialising the
	 * page. It emits some events that may be of interest to plugins.
	 *
	 * Once this method returns, the application is considered to have exited.
	 *
	 * @return int `self::ErrOk` on success, some other value on failure.
	 * @throws InvalidPluginException
	 * @throws InvalidPluginsDirectoryException
	 * @throws InvalidRoutesDirectoryException
	 * @throws InvalidRoutesFileException
	 */
	public function exec(): int
	{
		if (0 > version_compare(PHP_VERSION, $this->minimumPhpVersion())) {
			$appName = $this->title();

			if (empty($appName)) {
				$appName = "This application";
			}

			throw new RuntimeException("{$appName} is not able to run on this server.");
		}

		$this->m_isRunning = true;
		$this->loadPlugins();
		$this->loadRoutes();
		$this->emitEvent("application.executionstarted");
		$response = $this->handleRequest(Request::originalRequest());
		$this->emitEvent("application.executionfinished");

		$this->emitEvent("application.sendingresponse");
		$this->sendResponse($response);
		$this->emitEvent("application.responsesent");

		if (!empty($this->m_session["current_user"])) {
			$this->m_session["current_user"]["last_activity_time"] = time();
		}

		$this->flushTransientSessionData();
		$this->m_isRunning = false;
        return self::ExitOk;
	}
}
