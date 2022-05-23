<?php
/**
 * Defines the core Application class.
 *
 * @file Application.php
 * @author Darren Edale
 * @package libequit
 */

namespace Equit;

use DirectoryIterator;
use Equit\Contracts\Response;
use Equit\Contracts\Router as RouterContract;
use Equit\Exceptions\CsrfTokenVerificationException;
use Equit\Exceptions\ExpiredSessionIdUsedException;
use Equit\Exceptions\InvalidPluginException;
use Equit\Exceptions\InvalidPluginsDirectoryException;
use Equit\Exceptions\InvalidRoutesDirectoryException;
use Equit\Exceptions\InvalidRoutesFileException;
use Equit\Exceptions\UnroutableRequestException;
use Equit\Html\Page;
use Equit\Responses\DownloadResponse;
use Equit\Session\DataAccessor as SessionDataAccessor;
use Exception;
use Equit\Facades\Session as SessionFacade;
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
 * services to all plugins and other classes, and acts as a request dispatcher. An instance of the _Application_
 * class is the core of any application that uses the framework. Only a single _Application_ instance may be created
 * by any application. Applications using the framework create an instance of (a subclass of) the _Application_
 * class, call its _exec()_ method and wait for it to return.
 *
 * When the _exec()_ method is called it reads the received HTTP request, passes it to _handleRequest()_ for
 * dispatch, and returns. _handleRequest()_ works out which application plugin should be asked to handle the
 * received request and passes the request along to that plugin. The plugin then responds to the request and
 * returns, at which point application execution is complete.
 *
 * Some useful administrative information about the running application can be set using the _setTitle()_,
 * _setVersion()_ and _setMinimumPhpVersion()_ methods. The title and version are made available to any plugin
 * that fetches the running instance so that, for example, user messages can show the application title without
 * the individual plugins having to have it hard-coded.
 *
 * Applications that require a particular minimum version of PHP can set it using the _setMinimumPhpVersion()_
 * method. If this is done before _exec()_ is called, the application will exit with an appropriate error message
 * when _exec()_ is called if the PHP version on which the application is running is below the minimum set.
 *
 * For applications that make use of a data store, the _dataController()_ method provides access to the
 * DataController responsible for the interface between the application and the data store.
 *
 * The running _Application_ instance can be retrieved using the _instance()_ static method. This instance provides
 * access to all the services that the application provides. The _Application_ instance provides access to the
 * _Page_ object that represents the page being created in response to the request. It also provides methods for
 * sending data directly back to the client (_sendApiResponse()_, _sendDownload()_, _sendRawData()_) to support
 * asynchronous requests and non-HTML responses.
 *
 * ## Plugins
 * Plugins are loaded automatically by the _exec()_ method and are sourced from the _plugins/generic/_
 * subdirectory. Any plugin found in this directory is loaded. Subdirectories within the plugins directory are not
 * scanned. It is therefore sufficient to install a plugin's PHP file in the _plugins/generic/_ subdirectory for it
 * to be loaded and enabled by the application.
 *
 * While the _handleRequest()_ method automatically works out which plugin to use for any given request, it is
 * possible for plugins themselves to query the set of loaded plugins. This enables dependencies between plugins to
 * be handled, such that any plugin that relies on another being loaded can determine whether the plugin on which
 * it is dependent is present and adjust its behaviour accordingly. Plugins can be queried by name -
 * _pluginByName()_ - and by supported action - _pluginForAction()_.
 *
 * ## Requests
 * An internal request stack is maintained which enables plugins to submit additional custom-crafted Request
 * objects to _handleRequest()_. This is one way for plugins to ask other plugins to do something for them without
 * having to actually know about the details of the other plugin doing the work. The current request being handled
 * can always be retrieved using the _currentRequest()_ method. This returns the most recent request submitted to
 * _handleRequest()_ (in other words, the request on the top of the stack). In addition, the _originalRequest()_
 * method can be used to retrieve the original HTTP request that was submitted by the user agent (in other words
 * the request _exec()_ provided to _handleRequest()_, which is on the bottom of the stack).
 *
 * ## Inter-module communication
 * A simple inter-object communication mechanism is implemented by the Application class. This mechanism is based
 * on the concept of named events being emitted and objects subscribing to those events. Emitted events can
 * provide additional arguments that provide more details of the event (for example an event that fires when a
 * particular type of search has been executed might provide the search terms and result set as additional
 * arguments).
 *
 * Events are emitted by calling the _emitEvent()_ method. Subscriptions to events are achieved by calling the
 * _connect()_ method. Subscriptions can be unsubscribed by calling  _disconnect()_. Events do not need to be
 * registered or defined before they are emitted - it is sufficient just to call _emitEvent()_ in order to emit an
 * event. Any code -- plugin, class or even the main application script or _Application_ object -- can emit events
 * (indeed, the base _Application_ class emits a few).
 *
 * Emitters of events should take care to document the events they emit and the arguments that are provided with
 * them, and should strive to keep the signatures of their events stable (API stability) and the names of their
 * events distinct to avoid event naming clashes between different emitters.
 *
 * ## Session management
 * The _Application_ class can be used to manage session data in a way that guarantees clashes between plugins and
 * other applications running on the same domain are avoided. It implements a mechanism that is very simple to use
 * by hiding the complexities of keeping session data distinct behind simple, unique "context" strings. See the
 * _sessionData()_ method for details of how this works.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This module does not provide an API.
 *
 * ### Events
 * This module emits the following events.
 *
 * - `application.pluginsloaded`
 *   Emitted when the _exec()_ method has finished loading all the plugins.
 *
 * - `application.executionstarted`
 *   Emitted when _exec()_ starts actual execution (just before it calls _handleRequest()_).
 *
 * - `application.handlerequest.requestreceived($request)`
 *   Emitted when _handleRequest()_ receives a request to process.
 *
 *   `$request` _Request_ The request that was received.
 *
 * - `home.creatingtopsection`
 *   Emitted when the home page has been requested and the top section is being generated.
 *
 * - `home.creatingmiddlesection`
 *   Emitted when the home page has been requested and the middle section is being generated.
 *
 * - `home.creatingbottomsection`
 *   Emitted when the home page has been requested and the bottom section is being generated.
 *
 * - `application.handlerequest.routing(Request $request)`
 *   Emitted when `handleRequest()` is about to match the incoming Request to a route using the application's router.
 *
 * - `application.handlerequest.routed(Request $request)`
 *   Emitted when `handleRequest()` has successfully matched and routed the incoming `Request` to a route using the
 *   application's router.
 *
 * - `application.handlerequest.abouttofetchplugin`
 *   Emitted when _handleRequest()_ is about to fetch the plugin to handle the request it's been
 *   given.
 *
 * - `application.handlerequest.failedtofetchplugin`
 *   Emitted when _handleRequest()_ failed to find a suitable plugin for a request.
 *
 * - `application.handlerequest.pluginfetched($plugin)`
 *   Emitted when _handleRequest()_ finds a suitable plugin for a request.
 *
 *   `$plugin` _GenericPlugin_ The plugin found to handle the request.
 *
 * - `application.handlerequest.abouttoexecuteplugin($plugin)`
 *   Emitted immediately before _handleRequest()_ passes the request to the plugin to handle.
 *
 *   `$plugin` _GenericPlugin_ The plugin that is about to be asked to handle the request.
 *
 * - `application.executionfinished`
 *   Emitted by _exec()_ when _handleRequest()_ returns from processing the original HTTP request.
 *
 * - `application.abouttooutputpage`
 *   Emitted by _exec()_ when it is about to render the page to the client.
 *
 * - `application.pageoutputfinished`
 *   Emitted by _exec()_ when it has finished sending the page to the client.
 *
 * ### Connections
 * This module does not connect to any events.
 *
 * ### Settings
 * This module does not use any settings.
 *
 * ### Session Data
 * The Application class creates a session context with the identifier **application**.
 *
 * @actions _None_
 * @events application.pluginsloaded application.executionstarted application.handlerequest.requestreceived
 * home.creatingtopsection home.creatingmiddlesection home.creatingbottomsection
 * application.handlerequest.abouttofetchplugin application.handlerequest.failedtofetchplugin
 * application.handlerequest.pluginfetched application.handlerequest.abouttoexecuteplugin
 *     application.executionfinished application.abouttooutputpage application.pageoutputfinished
 * @connections _None_
 * @settings _None_
 * @session application
 * @aio-api _None_
 *
 * @class LibEquit\Application
 * @author Darren Edale
 * @package libequit
 *
 * @method static self instance()
 */
class WebApplication extends Application
{
	public const SessionDataContext = "application";
	protected const DefaultPluginsPath = "../plugins/generic";
	protected const DefaultPluginsNamespace = "";

	/** @var string Where plugins are loaded from. */
	private string $m_pluginsDirectory = self::DefaultPluginsPath;

	/** @var string The namespace where plugins are located. */
	private string $m_pluginsNamespace = self::DefaultPluginsNamespace;

	/** Application class's session data array. */
	protected ?SessionDataAccessor $m_session = null;

	/** Loaded plugin storage.*/
	private array $m_pluginsByName = [];
	private array $m_pluginsByAction = [];

	/** Stack of requests passed to handleRequest(). */
	private array $m_requestStack = [];

	/** @var bool True when exec() is in progress, false otherwise. */
	private bool $m_isRunning = false;

	/** @var RouterContract The router that routes requests to handlers. */
	private RouterContract $m_router;

	/** @var Page The page template to use. */
	private Page $m_page;

	/**
	 * Construct a new Application object.
	 *
	 * Application is a singleton class. Once an instance has been created, attempts to create another will trigger
	 * a fatal error.
	 *
	 * @param $appRoot string The path to the root of the application. This helps locate files (e.g. config files).
	 * @param $dataController DataController|null The data controller for the application.
	 * @param Page|null $pageTemplate
	 *
	 * @throws \Exception if an Application instance has already been created.
	 */
	public function __construct(string $appRoot, ?DataController $dataController = null, ?Page $pageTemplate = null)
	{
		parent::__construct($appRoot, $dataController);
		$this->initialiseSession();
		$this->m_session = $this->sessionData(self::SessionDataContext);
		$this->setRouter(new Router());
		$this->setPage($pageTemplate ?? new Page());
	}

	/** Determine whether the application is currently running or not.
	 *
	 * The application is running if its _exec()_ method has been called and has not yet returned.
	 *
	 * @return bool _true_ if the application is running, _false_ otherwise.
	 */
	public function isRunning(): bool
	{
		return $this->m_isRunning;
	}

	/**
	 * Set the plugins directory.
	 *
	 * The plugins directory can only be set before exec() is called. If exec() has been called, calling
	 * setPluginsDirectory() will fail.
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
     * Initialise the application session data.
     */
    private function initialiseSession(): void
    {
        SessionFacade::start();
        // ensure the CSRF token is generated as soon as the session is started
        $this->csrf();
    }

    /**
	 * Don't set the page after content has been generated or added to it, unless you want to discard that content.
	 *
	 * @param Page|null $page The page to use or `null` to unset the existing page.
	 */
	public function setPage(?Page $page): void
	{
		$this->m_page = $page;
	}

    /**
     * Fetch the application's session data.
     *
     * The context parameter ensures that different parts of the application can keep their session data separate from
     * other parts and therefore avoid namespace clashes and so on.
     *
     * The session data for the context is returned as a PrefixedAccessor, a view on a subset of the session data whose
     * keys all share a prefix. Changes made to the returned object will show up in the session data, all prefixed with
     * the given context (i.e. you don't need to keep using the context when setting session data).
     *
     * @param $context string A unique context identifier for the session data.
     *
     * @return SessionDataAccessor The session data for the given context.
     * @throws InvalidArgumentException If an empty context is given.
     */
    public function sessionData(string $context): SessionDataAccessor
    {
        if (empty($context)) {
            throw new InvalidArgumentException("Session context must not be empty.");
        }

        return SessionFacade::prefixed("{$context}.");
    }

    /**
     * Fetch the application's page object.
	 *
	 * The page object is the page where all of the application's output is generated. Client code should almost
	 * never output content directly - it should almost always be inserted into the page object.
	 *
	 * @return Page The application's page.
	 * @throws RuntimeException if there is no Page set.
	 */
	public function page(): Page
	{
		assert(!is_null($this->m_page), new RuntimeException("Application has no Page object set."));
		return $this->m_page;
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
	 * Various checks are performed to ensure that the path represents a genuine plugin for the application. If it
	 * does, it is loaded and added to the application's set of available plugins. Each plugin is guaranteed to be
	 * loaded just once.
	 *
	 * Plugins are required to meet the following conditions:
	 * - defined in a file named exactly as the plugin class is named, with the extension ".php"
	 * - define a class that inherits the *\LibEquit\GenericPlugin* base class
	 * - provide a valid instance of the appropriate class from the _instance()_ method of the main plugin class
	 *   defined in the file
	 * - provide a valid set of supported actions from the _supportedActions()_ method of the main plugin class
	 *   defined in the file.
	 *
	 * ### Note
	 * An empty set of supported actions is valid - it is perfectly acceptable for a plugin to exist solely to
	 * respond to emitted events.
	 *
	 * ### Todo
	 * - How to allow plugin classes to exist in a namespace other than the global namespace?
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

		if (!$pluginClassInfo->isSubclassOf(GenericPlugin::class)) {
			throw new InvalidPluginException($path, null, "Plugin file \"{$path}\" contains the class \"{$className}\" which does not implement " . GenericPlugin::class);
		}

		try {
			$instanceFn = $pluginClassInfo->getMethod("instance");
		}
		catch (ReflectionException $err) {
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

		if ($instanceFnReturnType->isBuiltin() || ("self" != $instanceFnReturnType->getName() && !is_a($instanceFnReturnType->getName(), GenericPlugin::class, true))) {
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

		try {
			$actionsFn = $pluginClassInfo->getMethod("supportedActions");
		}
		catch (ReflectionException $err) {
			throw new InvalidPluginException($path, $plugin, "Exception introspecting {$className}::supportedActions() method: [{$err->getCode()}] {$err->getMessage()}", 0, $err);
		}

		if (!$actionsFn->isPublic() || !$actionsFn->isStatic()) {
			throw new InvalidPluginException($path, $plugin, "{$className}::supportedActions() method must be public static");
		}

		if (0 != $actionsFn->getNumberOfRequiredParameters()) {
			throw new InvalidPluginException($path, $plugin, "{$className}::supportedActions() method must be callable with no arguments");
		}

		$actionsFnReturnType = $actionsFn->getReturnType();

		if (!$actionsFnReturnType) {
			throw new InvalidPluginException($path, $plugin, "{$className}::supportedActions() has no return type.");
		}

		if (!$actionsFnReturnType->isBuiltin() || "array" != $actionsFnReturnType->getName()) {
			throw new InvalidPluginException($path, $plugin, "{$className}::supportedActions() must return an array of strings");
		}

        try {
            $actions = $actionsFn->invoke(null);
        }
        catch (ReflectionException $err) {
			throw new InvalidPluginException($path, $plugin, "Exception invoking {$className}::supportedActions(): [{$err->getCode()}] {$err->getMessage()}", 0, $err);
        }

		if (!is_array($actions)) {
			throw new InvalidPluginException($path, $plugin, "{$className}::supportedActions() did not provide a list of supported actions");
		}

		foreach ($actions as $action) {
			if (!is_string($action)) {
				throw new InvalidPluginException($path, $plugin, "{$className} plugin listed an invalid supported action: " . stringify($action));
			}

			$action = strtolower($action);

			if (isset($this->m_pluginsByAction[$action])) {
				AppLog::warning("{$className} plugin listed a supported action that is already taken by the " . get_class($this->m_pluginsByAction[$action]) . " plugin.", __FILE__, __LINE__, __FUNCTION__);
				continue;
			}

			$this->m_pluginsByAction[$action] = $plugin;
		}

		$this->m_pluginsByName[$classNameKey] = $plugin;
	}

	/** Load all the available plugins.
	 *
	 * Plugins are loaded from the default plugins path. All valid plugins found are loaded and instantiated. The
	 * error log will contain details of any plugins that failed to load.
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
			$pluginLoadOrder = $this->config("app.plugins.generic.loadorder", []);

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
     * If the plugin has been loaded, the created instance of that plugin will be returned.
     *
     * Plugins can use this method to fetch instances of any other plugins on which they depend. If this method
     * returns _null_ then plugins should assume that the plugin on which they depend is not available and act
     * accordingly.
     *
     * @param $name string The class name of the plugin.
     *
     * @return GenericPlugin|null The loaded plugin instance if the named plugin was loaded, _null_ otherwise.
     * @throws InvalidPluginException if a plugin is loaded that is not valid.
     * @throws InvalidPluginsDirectoryException if the configured directory for plugins is not valid.
     */
	public function pluginByName(string $name): ?GenericPlugin
	{
		$this->loadPlugins();
		return $this->m_pluginsByName[mb_strtolower($name, "UTF-8")] ?? null;
	}

	/**
	 * Fetch the plugin that is registered to handle a named action.
	 *
	 * @param $action string The action whose registered plugin is sought.
	 *
	 * @return GenericPlugin|null The plugin registered to handle the named action, or _null_ if no plugin is
	 * registered for the action.
	 */
	public function pluginForAction(string $action): ?GenericPlugin
	{
		$action = mb_strtolower($action, "UTF-8");
		$this->loadPlugins();
		return $this->m_pluginsByAction[mb_strtolower($action, "UTF-8")] ?? null;
	}

	/**
	 * Send a download to the user.
	 *
	 * If you want to set the MIME type but not the file name, provide an empty string for the file name. If either
	 * contains any invalid characters for use in a HTTP header line, a corrupt download is very likely to result.
	 *
	 * @param $data string the file to send.
	 * @param $fileName string the file name to specify for the user's download.
	 * @param $contentType string _optional_ the MIME type for the download.
	 * @param $headers array[string=>string] _optional_ Additional headers to send with the download.
	 * @deprecated Build a DownloadResponse or a FileDownloadResponse instead.
	 */
	public function sendDownload(string $data, string $fileName, string $contentType = "application/octet-stream", array $headers = []): void
	{
		$this->sendResponse((new DownloadResponse($data))->ofType($contentType)->withHeaders($headers)->named($fileName));
	}

	/**
	 * Send raw data to the user.
	 *
	 * @param $data string the data to send.
	 * @param $mimeType string|null _optional_ the MIME type for the download.
	 * @param $headers array[string=>string] _optional_ Additional headers to send with the download.
	 */
	public function sendRawData(string $data, ?string $mimeType = null, array $headers = []): bool
	{
		ob_end_clean();

		if (!empty($mimeType)) {
			header("content-type: $mimeType", true);
		} else {
			header("content-type: application/octet-stream", true);
		}

		foreach ($headers as $name => $value) {
			header("$name: $value", false);
		}

		echo $data;
		exit(0);
	}

	/**
	 * Send the response for an API call.
	 *
	 * API responses always take the following form:
	 *      {code}[ {message}]
	 *      {data}
	 *
	 * - _{code}_ is always an integer. It is 0 on success, non-0 on failure.
	 *
	 * - _{message}_ is an optional string containing a message that can be presented to the end user. Its primary
	 *   use is as an explanatory message in case of failure (_{code}_ != 0).
	 *
	 * _{data}_ is the data returned by the API call. The format of the data is defined by the API call itself.
	 *
	 * ### Warning
	 * This method is guaranteed not return.
	 *
	 * @param $code int The response code.
	 * @param $message string _optional_ A message to go with the code.
	 * @param $data string _optional_ The data to send as the response.
	 */
	public function sendApiResponse(int $code, string $message = "", string $data = ""): void
	{
		if (0 != ob_get_level() && !ob_end_clean()) {
			AppLog::error("failed to clear output buffer before sending API response (requested action = \"" . $this->currentRequest()->action() . "\"; response = \"$code $message\")", __FILE__, __LINE__, __FUNCTION__);
		}

		echo "{$code}" . (empty($message) ? "" : " $message") . "\n{$data}";
		exit(0);
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

	/** Push a request onto the request stack.
	 *
	 * This is a private internal method that maintains the request stack that is used to provide plugins with
	 * access to the current request. It should only be used by LibEquit\Application::handleRequest()
	 *
	 * @param $request Request The request to push onto the stack.
	 */
	protected function pushRequest(Request $request): void
	{
		$this->m_requestStack[] = $request;
	}

	/** Pop a request from the request stack.
	 *
	 * This is a private internal method that maintains the request stack that is used to provide plugins with
	 * access to the current request. It should only be used by LibEquit\Application::handleRequest()
	 */
	protected function popRequest(): void
	{
		array_pop($this->m_requestStack);
	}

	/**
	 * Fetch the current request.
	 *
	 * The current request is peeked from the top of the request stack.
	 *
	 * @see-also @link originalRequest()
	 *
	 * @return Request|null The current request being handled, or _null_ if the request stack is empty.
	 */
	public function currentRequest(): ?Request
	{
		$n = count($this->m_requestStack);
		return (0 < $n ? $this->m_requestStack[$n - 1] : null);
	}

	/** Fetch the original request submitted by the user.
	 *
	 * This method fetches the original request received from the user. It is just a convenience synonym for
	 * LibEquit\Request::originalRequest().
	 *
	 * @see-also currentRequest()
	 *
	 * @return Request The user's original request.
	 */
	public function originalRequest(): Request
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
		if (!SessionFacade::has("csrf-token")) {
			$this->regenerateCsrf();
		}

		return SessionFacade::get("csrf-token");
	}

	/**
	 * Force the CSRF token to be regenerated.
	 *
	 * By default a 64-character random string is generated.
	 */
	public function regenerateCsrf(): void
	{
		SessionFacade::set("csrf-token", randomString(64));
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

		if (!hash_equals($this->csrf(), $this->csrfTokenFromRequest($request))) {
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
	 * @return Response|null An optional Response to send to the client. For legacy support, if no response is returned
	 * exec() assumes that content has been added to the Page instance and that is output instead.
	 * @throws CsrfTokenVerificationException if the request requires CSRF verification and fails
	 */
	public function handleRequest(Request $request): ?Response
	{
		$this->pushRequest($request);
		$this->emitEvent("application.handlerequest.requestreceived", $request);
		$this->verifyCsrf($request);

		// if legacy `action` URL parameter is supported and the request path info is '/', any registered route handler
		// for / - which is likely - will override the 'action' URL parameter, so in this scenario we skip the router
		$useAction = $this->config("app.legacy.support-action-url-parameter", true);
		$skipRouter = $useAction && "/" === $request->pathInfo() && $request->hasUrlParameter("action");

		if (!$skipRouter) {
			try {
				$this->emitEvent("application.handlerequest.routing", $request);
				$response = $this->router()->route($request);
				$this->emitEvent("application.handlerequest.routed", $request);
				$useAction = false;
			}
			catch (UnroutableRequestException $err) {
				// fall back on deprecated use of special `action` URL parameter
				AppLog::warning("Falling back on deprecated 'action' URL parameter to handle {$request->method()} request '{$request->rawUrl()}'", __FILE__, __LINE__, __FUNCTION__);
			}
		}

		if ($useAction) {
			$action = mb_strtolower($request->action(), "UTF-8");

			if (empty($action) || "home" == $action) {
				// TODO 404 page
				return null;
			}

			$this->emitEvent("application.handlerequest.abouttofetchplugin");
			$plugin = $this->pluginForAction($action);

			if (!$plugin) {
				// TODO 404 page
				$this->emitEvent("application.handlerequest.failedtofetchplugin");
				$this->popRequest();
				return null;
			}

			$this->emitEvent("application.handlerequest.pluginfetched", $plugin);
			$this->emitEvent("application.handlerequest.abouttoexecuteplugin", $plugin);
			$response = $plugin->handleRequest($request);
		}

		$this->popRequest();
		return (isset($response) && ($response instanceof Response) ? $response : null);
	}

	/**
	 * Execute the application.
	 *
	 * Start execution of the application. This method will pass the original request from the user to
	 * _handleRequest()_ and return when processing of that request completes. This method should never be called,
	 * except from the script that is in use as the application bootstrap.
	 *
	 * This method is responsible for setting up the execution context for the request, including initialising the
	 * page. It emits some events that may be of interest to plugins.
	 *
	 * Once this method returns, the application is considered to have exited.
	 *
	 * @return int 0
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

		if (isset($response)) {
			$this->emitEvent("application.sendingresponse");
			$this->sendResponse($response);
			$this->emitEvent("application.responsesent");
		} else {
			$this->emitEvent("application.abouttooutputpage");
			$this->page()->output();
			$this->emitEvent("application.pageoutputfinished");
		}

		if (!empty($this->m_session["current_user"])) {
			$this->m_session["current_user"]["last_activity_time"] = time();
		}

		$this->m_isRunning = false;
        return self::ExitOk;
	}
}
