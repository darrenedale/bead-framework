<?php
/**
 * Defines the core Application class.
 *
 * @file Application.php
 * @author Darren Edale
 * @package libequit
 */

namespace Equit {
	use Equit\Html\HtmlLiteral;
	use Equit\Html\Page;
	use ReflectionClass;
	use ReflectionException;

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
	 * - **application.pluginsloaded**
	 *   Emitted when the _exec()_ method has finished loading all the plugins.
	 *
	 * - **application.executionstarted**
	 *   Emitted when _exec()_ starts actual execution (just before it calls _handleRequest()_).
	 *
	 * - **application.handlerequest.requestreceived($request)**
	 *   Emitted when _handleRequest()_ receives a request to process.
	 *
	 *   **$request** _Request_ The request that was received.
	 *
	 * - **home.creatingtopsection**
	 *   Emitted when the home page has been requested and the top section is being generated.
	 *
	 * - **home.creatingmiddlesection**
	 *   Emitted when the home page has been requested and the middle section is being generated.
	 *
	 * - **home.creatingbottomsection**
	 *   Emitted when the home page has been requested and the bottom section is being generated.
	 *
	 * - **application.handlerequest.abouttofetchplugin**
	 *   Emitted when _handleRequest()_ is about to fetch the plugin to handle the request it's been
	 *   given.
	 *
	 * - **application.handlerequest.failedtofetchplugin**
	 *   Emitted when _handleRequest()_ failed to find a suitable plugin for a request.
	 *
	 * - **application.handlerequest.pluginfetched($plugin)**
	 *   Emitted when _handleRequest()_ finds a suitable plugin for a request.
	 *
	 *   **$plugin** _GenericPlugin_ The plugin found to handle the request.
	 *
	 * - **application.handlerequest.abouttoexecuteplugin($plugin)**
	 *   Emitted immediately before _handleRequest()_ passes the request to the plugin to handle.
	 *
	 *   **$plugin** _GenericPlugin_ The plugin that is about to be asked to handle the request.
	 *
	 * - **application.executionfinished**
	 *   Emitted by _exec()_ when _handleRequest()_ returns from processing the original HTTP request.
	 *
	 * - **application.abouttooutputpage**
	 *   Emitted by _exec()_ when it is about to render the page to the client.
	 *
	 * - **application.pageoutputfinished**
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
	 */
	class Application {
		const SessionDataContext = "application";
		protected const DefaultPluginsPath = "plugins/generic";
		protected const DefaultLibsPath = "";

		/** The singleton instance. */
		private static $s_instance = null;

		/** @var DataController The data controller. */
		private $m_dataController = null;

		/** @var string Where plugins are loaded from. */
		private $m_pluginsPath = self::DefaultPluginsPath;

		/** @var string Where the application should look for any third-party content (including Equit lib). */
		private $m_libsPath = self::DefaultLibsPath;

		/** @var string Optional application title. */
		private $m_title = "";

		/** @var string Optional application version string. */
		private $m_version = "";

		/** The minimum PHP version the app requires to run. */
		private $m_minimumPhpVersion = "0.0.0";

		/** Application class's session data array. */
		protected $m_session = null;

		/** Loaded plugin storage.*/
		private $m_pluginsByName = [];
		private $m_pluginsByAction = [];

		/** event callback storage. */
		private $m_eventCallbacks = [];

		/** Stack of requests passed to handleRequest(). */
		private $m_requestStack = [];

		private $m_isRunning = false;

		/** @var Page The page template to use. */
		private $m_page;

		/** @var null|\Equit\Translator The currently installed translator */
		private $m_translator = null;

		/**
		 * Construct a new Application object.
		 *
		 * Application is a singleton class. Once an instance has been created, attempts to create another will trigger
		 * a fatal error.
		 *
		 * @param $dataController DataController|null The data controller for the application.
		 * @param Page|null $pageTemplate
		 */
		public function __construct(?DataController $dataController = null, ?Page $pageTemplate = null) {
			if(isset(self::$s_instance)) {
				AppLog::error("attempt to create more than one application", __FILE__, __LINE__, __FUNCTION__);
				trigger_error(tr("A fatal application error occurred. Please contact the system administrator (%1).", __FILE__, __LINE__, "ERR_APPLICATION_MULTIPLE_INSTANCE"), E_USER_ERROR);
			}

			if(!defined("app.uid")) {
				AppLog::error("missing \"app.uid\" in config file", __FILE__, __LINE__, __FUNCTION__);
				trigger_error(tr("The application is not configured correctly. Please contact the system administrator (ERR_APPLICATION_MISSING_UID)."), E_USER_ERROR);
			}

			self::$s_instance = $this;
			$this->setLibraryPath(constant("app.libs.path") ?? self::DefaultLibsPath);
			self::initialiseSession();

			$this->m_session = &$this->sessionData(self::SessionDataContext);

			$this->m_translator = new Translator();
			$this->m_translator->addSearchPath("i18n");

			// do this first in case the setting contains an invalid language specifier
			$this->m_translator->setLanguage("en-GB");
			$this->setDataController($dataController);
			$this->setPage($pageTemplate);
		}

		/**
		 * Initialise the application session data.
		 */
		private static function initialiseSession(): void {
			static $s_basePath = null;

			if(is_null($s_basePath)) {
				$s_basePath = ["app", constant("app.uid")];
			}

			session_start();
			$session =& $_SESSION;

			foreach($s_basePath as $p) {
				if(!array_key_exists($p, $session) || !is_array($session[$p])) {
					$session[$p] = [];
				}

				$session =& $session[$p];
			}
		}

		/**
		 * Fetch the single instance of the LibEquit\Application class.
		 *
		 * @return Application The instance.
		 */
		public static function instance(): Application {
			return self::$s_instance;
		}

		/** Set the minimum PHP version the application requires.
		 *
		 * The version string should be of the form _x.y.z_ where _x_, _y_ and _z_ are integers >= 0.
		 *
		 * @param $v string The minimum required PHP version.
		 *
		 * @return void.
		 */
		public function setMinimumPhpVersion(string $v): void {
			$this->m_minimumPhpVersion = $v;
		}

		/** Fetch the minimum PHP version the application requires.
		 *
		 * This will be *0.0.0* by default, effectively meaning any PHP version is acceptable. (Note in reality PHP
		 * 7 or later is required by this library.)
		 *
		 * @return string The minimum PHP version.
		 */
		public function minimumPhpVersion(): string {
			return $this->m_minimumPhpVersion;
		}

		/** Set the application's title.
		 *
		 * @param $title string The title.
		 */
		public function setTitle(string $title) {
			$this->m_title = $title;
		}

		/** Fetch the application's title.
		 *
		 * @return string The title.
		 */
		public function title(): string {
			return $this->m_title;
		}

		/**
		 * Set the application's version.
		 *
		 * @param string $version The version string.
		 */
		public function setVersion(string $version) {
			$this->m_version = $version;
		}

		/**
		 * Fetch the application's version.
		 *
		 * @return string The version string.
		 */
		public function version(): string {
			return $this->m_version;
		}

		/** Set the application's data controller.
		 *
		 * The data controller mediates all interaction between the application (including classes and plugins) and the
		 * database.
		 *
		 * @param $controller DataController|null The data controller to use.
		 */
		public function setDataController(?DataController $controller): void {
			$this->m_dataController = $controller;
		}

		/** Fetch the application's data controller.
		 *
		 * The returned data controller should be used by all classes and plugins whenever access to the database is
		 * required.
		 *
		 * @return DataController|null The data controller.
		 */
		public function dataController(): ?DataController {
			return $this->m_dataController;
		}

		/**
		 * Set the plugins path.
		 *
		 * The plugins path can only be set before exec() is called. If exec() has been called, calling setPluginPath()
		 * will fail.
		 *
		 * @param string $path The path to load plugins from.
		 *
		 * @return bool `true` If the provided path was valid and was set, `false` otherwise.
		 */
		public function setPluginsPath(string $path): bool {
			if($this->isRunning()) {
				AppLog::error("can't set plugins path while application is running", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!preg_match("|[a-zA-Z0-9_-][/a-zA-Z0-9_-]*|", $path)) {
				AppLog::error("invalid plugins path: \"$path\"", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$this->m_pluginsPath = $path;
			return true;
		}

		/**
		 * Fetch the plugins path.
		 *
		 * This is the path from which plugins will be/were loaded.
		 *
		 * @return string The plugins path.
		 */
		public function pluginsPath(): string {
			return $this->m_pluginsPath;
		}

		/**
		 * Set the library path.
		 *
		 * The library path can only be set before exec() is called. If exec() has been called, calling setPluginPath()
		 * will fail.
		 *
		 * The library path is where your application should look for third-party content. It can be useful for
		 * including PHP files, setting the path to runtime scripts and stylesheets, etc.
		 *
		 * @param string $path The base path where third party libraries are installed.
		 *
		 * @return bool `true` If the provided path was valid and was set, `false` otherwise.
		 */
		public function setLibraryPath(string $path): bool {
			if($this->isRunning()) {
				AppLog::error("can't set library path while application is running", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!preg_match("|[a-zA-Z0-9_-][/a-zA-Z0-9_-]*|", $path)) {
				AppLog::error("invalid library path: \"$path\"", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$this->m_libsPath = $path;
			return true;
		}

		/**
		 * Fetch the library path.
		 *
		 * @param $libName string The third-party library whose path is sought.
		 *
		 * This is the path from which third-party content will be loaded. You can specify the library name and it will
		 * be appended to the path. You can also use this argument in reimplementations of this method to customise
		 * the path according to the library, if that's what your application needs.
		 *
		 * @return string The library path.
		 */
		public function libraryPath(string $libName = ""): string {
			if(empty($libName)) {
				return $this->m_libsPath;
			}

			// TODO sanitise library name?
			return "{$this->m_libsPath}/$libName";
		}

		/** Determine whether the application is currently running or not.
		 *
		 * The application is running if its _exec()_ method has been called and has not yet returned.
		 *
		 * @return bool _true_ if the application is running, _false_ otherwise.
		 */
		public function isRunning(): bool {
			return $this->m_isRunning;
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
		public function isInDebugMode(): bool {
			return true === constant("app.debugmode");
		}

		/**
		 * Fetch the application's session data.
		 *
		 * This method should be used to access the session data rather than using _$_SESSION_ directly as it ensures
		 * that the application's session data is kept separate from any other session data that is using the same
		 * domain name. Use of the context parameter ensures that different parts of the application can keep their
		 * session data separate from other parts and therefore avoid namespace clashes and so on.
		 *
		 * The session data for the context is returned as an array reference, which calling code will need to assign
		 * by
		 * reference in order to use successfully. If it is not assigned by reference, any changes made to the provided
		 * session array will not persist between requests. To do this, do something like the following in your code:
		 *
		 *     $mySession = & LibEquit\Application::instance()->sessionData("mycontext");
		 *
		 * Once you have done this, you can use _$mySession_ just like you would use _$_SESSION_ to store your session
		 * data.
		 *
		 * There is nothing special that needs to be done to create a new session context. If a request is made for a
		 * context that does not already exist, a new one is automatically initialised and returned.
		 *
		 * @param $context string A unique context identifier for the session data.
		 *
		 * @return array[mixed => mixed] A reference to the session data for the given context.
		 */
		public function & sessionData(string $context): array {
			if(empty($context)) {
				return null;
			}

			// ensure context is not numeric (avoids issues when un-serialising session data)
			$context = "c$context";
			$session = &$_SESSION["app"][constant("app.uid")];

			if(!isset($session[$context])) {
				$session[$context] = [];
			}

			$session = &$session[$context];
			return $session;
		}

		/**
		 * Don't set the page after content has been generated or added to it, unless you want to discard that content.
		 *
		 * @param \Equit\Html\Page|null $page The page to use or `null` to unset the existing page.
		 */
		public function setPage(?Page $page): void {
			$this->m_page = $page;
		}

		/** Fetch the application's page object.
		 *
		 * The page object is the page where all of the application's output is generated. Client code should almost
		 * never output content directly - it should almost always be inserted into the page object.
		 *
		 * @return Page The application's page.
		 */
		public function page(): Page {
			return $this->m_page;
		}

		/** Fetch the application's translator.
		 *
		 * The application's translator handles translation of strings into the user's chosen language. Client code
		 * should never need to use this method: it is far simpler to use the tr() function.
		 *
		 * @return Translator|null The translator.
		 */
		public function translator(): ?Translator {
			return $this->m_translator;
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
		 * @return bool true if the plugin was loaded successfully, false otherwise.
		 */
		private function loadPlugin(string $path): bool {
			if(!is_file($path) || !is_readable($path)) {
				AppLog::error("plugin path \"$path\" is not a file or is not readable", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(".php" != substr($path, -4)) {
				AppLog::error("plugin path \"$path\" is not a .php file", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			// NOTE this currently requires plugins to be in the global namespace
			$className    = basename($path, ".php");
			$classNameKey = mb_convert_case($className, MB_CASE_LOWER, "UTF-8");

			if(isset($this->m_pluginsByName[$classNameKey])) {
				/* already loaded */
				return false;
			}

			/** @noinspection PhpIncludeInspection */
			include_once($path);

			if(!class_exists($className)) {
				AppLog::error("plugin file \"$path\" does not define the \"$className\" class", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			try {
				$pluginClassInfo = new ReflectionClass($className);
			}
			catch(ReflectionException $err) {
				AppLog::error("failed to introspect plugin class $className: [" . $err->getCode() . "] " . $err->getMessage(), __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!$pluginClassInfo->isSubclassOf(GenericPlugin::class)) {
				AppLog::error("plugin file \"$path\" contains the class \"$className\" which does not implement " . GenericPlugin::class, __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			try {
				$instanceFn = $pluginClassInfo->getMethod("instance");
			}
			catch(ReflectionException $err) {
				AppLog::error("exception introspecting $className::instance() method: [" . $err->getCode() . "] " . $err->getMessage(), __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!$instanceFn->isPublic() || !$instanceFn->isStatic()) {
				AppLog::error("$className::instance() method must be public static", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(0 != $instanceFn->getNumberOfRequiredParameters()) {
				AppLog::error("$className::instance() method must be callable with no arguments", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$instanceFnReturnType = $instanceFn->getReturnType();

			if(!$instanceFnReturnType) {
				AppLog::error("$className::instance() has no return type", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if($instanceFnReturnType->isBuiltin() || GenericPlugin::class != (string) $instanceFnReturnType) {
				AppLog::error("$className::instance() must return an instance of $className", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$plugin = $instanceFn->invoke(null);

			if(!$plugin instanceof $className) {
				AppLog::error("the method $className::instance() did not provide an object of the (correct) plugin class", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			try {
				$actionsFn = $pluginClassInfo->getMethod("supportedActions");
			}
			catch(ReflectionException $err) {
				AppLog::error("exception introspecting $className::supportedActions() method: [" . $err->getCode() . "] " . $err->getMessage(), __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!$actionsFn->isPublic() || !$actionsFn->isStatic()) {
				AppLog::error("$className::supportedActions() method must be public static", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(0 != $actionsFn->getNumberOfRequiredParameters()) {
				AppLog::error("$className::supportedActions() method must be callable with no arguments", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$actionsFnReturnType = $actionsFn->getReturnType();

			if(!$actionsFnReturnType) {
				AppLog::error("$className::supportedActions() has no return type", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!$actionsFnReturnType->isBuiltin() || "array" != (string) $actionsFnReturnType) {
				AppLog::error("$className::supportedActions() must return an array of strings", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$actions = $actionsFn->invoke(null);

			if(!is_array($actions)) {
				AppLog::error("the method $className::supportedActions() did not provide a list of supported actions", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			foreach($actions as $action) {
				if(!is_string($action)) {
					AppLog::error("the $className plugin listed an invalid supported action: " . stringify($action), __FILE__, __LINE__, __FUNCTION__);
					continue;
				}

				$action = strtolower($action);

				if(isset($this->m_pluginsByAction[$action])) {
					AppLog::warning("the $className plugin listed a supported action that is already taken by the " . get_class($this->m_pluginsByAction[$action]) . " plugin.", __FILE__, __LINE__, __FUNCTION__);
					continue;
				}

				$this->m_pluginsByAction[$action] = $plugin;
			}

			$this->m_pluginsByName[$classNameKey] = $plugin;
			return true;
		}

		/** Load all the available plugins.
		 *
		 * Plugins are loaded from the default plugins path. All valid plugins found are loaded and instantiated. The
		 * error log will contain details of any plugins that failed to load.
		 *
		 * @return bool true if the plugins path was successfully scanned for plugins, false otherwise.
		 */
		protected function loadPlugins(): bool {
			static $s_done = false;

			if(!$s_done) {
				if(!is_dir($this->m_pluginsPath)) {
					AppLog::error("plugin path \"$this->m_pluginsPath\" is not a directory", __FILE__, __LINE__, __FUNCTION__);
					return false;
				}

				if(!is_readable($this->m_pluginsPath)) {
					AppLog::error("plugin path \"$this->m_pluginsPath\" is not readable", __FILE__, __LINE__, __FUNCTION__);
					return false;
				}

				/* load the ordered plugins, then the rest after */
				if(defined("app.plugins.generic.loadorder")) {
					$pluginLoadOrder = constant("app.plugins.generic.loadorder");

					if(!is_array($pluginLoadOrder)) {
						AppLog::warning("app.plugins.generic.loadorder specified in config but is not an array", __FILE__, __LINE__, __FUNCTION__);
					}
					else {
						foreach($pluginLoadOrder as $pluginName) {
							$path = @realpath($this->m_pluginsPath . DIRECTORY_SEPARATOR . $pluginName . ".php");

							if(is_string($path)) {
								$this->loadPlugin($path);
							}
						}
					}
				}

				$d = dir($this->m_pluginsPath);

				if(!$d) {
					AppLog::error("entries in plugin path \"$this->m_pluginsPath\" could not listed", __FILE__, __LINE__, __FUNCTION__);
					return false;
				}

				while(false != ($f = $d->read())) {
					if("." == $f || ".." == $f) {
						continue;
					}

					$this->loadPlugin(realpath($this->m_pluginsPath . DIRECTORY_SEPARATOR . $f));
				}

				$s_done = true;
				$this->emitEvent("application.pluginsloaded");
			}

			return true;
		}

		/**
		 * Fetch the list of loaded plugins.
		 *
		 * @return array[string] The names of the loaded plugins.
		 */
		public function loadedPlugins(): array {
			return array_keys($this->m_pluginsByName);
		}

		/**
		 * Fetch a plugin by its name.
		 *
		 * If the plugin has been loaded, the created instance of that plugin will be returned.
		 *
		 * Plugins can use this method to fetch instances of any other plugins on which they depend. If this method
		 * returns
		 * _null_ then plugins should assume that the plugin on which they depend is not available and act accordingly.
		 *
		 * @param $name string The class name of the plugin.
		 *
		 * @return GenericPlugin|null The loaded plugin instance if the named plugin was loaded, _null_ otherwise.
		 */
		public function pluginByName(string $name): ?GenericPlugin {
			$name = strtolower($name);
			$this->loadPlugins();

			if(!isset($this->m_pluginsByName[$name])) {
				AppLog::warning("no plugin named \"$name\"", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			return $this->m_pluginsByName[$name];
		}

		/**
		 * Fetch the plugin that is registered to handle a named action.
		 *
		 * @param $action string The action whose registered plugin is sought.
		 *
		 * @return GenericPlugin|null The plugin registered to handle the named action, or _null_ if no plugin is
		 * registered for the action.
		 */
		public function pluginForAction(string $action): ?GenericPlugin {
			$action = mb_convert_case($action, MB_CASE_LOWER, "UTF-8");
			$this->loadPlugins();

			if(!isset($this->m_pluginsByAction[$action])) {
				AppLog::warning("no plugin for action \"$action\"", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			return $this->m_pluginsByAction[$action];
		}

		/**
		 * Send a download to the user.
		 *
		 * If you want to set the MIME type but not the file name, provide an empty string for the file name. If either
		 * contains any invalid characters for use in a HTTP header line, a corrupt download is very likely to result.
		 *
		 * @param $data string the file to send.
		 * @param $fileName string the file name to specify for the user's download.
		 * @param $mimeType string _optional_ the MIME type for the download.
		 * @param $headers array[string=>string] _optional_ Additional headers to send with the download.
		 */
		public function sendDownload(string $data, string $fileName, string $mimeType = "application/octet-stream", array $headers = []): void {
			if(0 != ob_get_level() && !ob_end_clean()) {
				AppLog::error("failed to clear output buffer before sending file download (requested action = \"" . $this->currentRequest()->action() . "\")", __FILE__, __LINE__, __FUNCTION__);
			}

			if(empty($mimeType)) {
				header("content-type: application/octet-stream", true);
			}
			else {
				header("content-type: $mimeType", true);
			}

			header("content-disposition: attachment; filename=\"" . ($fileName ?? "downloaded_file") . "\"", true);

			foreach($headers as $name => $value) {
				header("$name: $value", false);
			}

			echo $data;
			exit(0);
		}

		/**
		 * Send raw data to the user.
		 *
		 * @param $data string the data to send.
		 * @param $mimeType string|null _optional_ the MIME type for the download.
		 * @param $headers array[string=>string] _optional_ Additional headers to send with the download.
		 *
		 * @return bool _false_ if the send failed. On success, the application will exit with return code 0 before the
		 *     end of the method is reached (i.e. it will not return).
		 */
		public function sendRawData(string $data, ?string $mimeType = null, array $headers = []): bool {
			if(is_string($data)) {
				ob_end_clean();

				if(!empty($mimeType)) {
					header("content-type: $mimeType", true);
				}
				else {
					header("content-type: application/octet-stream", true);
				}

				foreach($headers as $name => $value) {
					header("$name: $value", false);
				}

				echo $data;
				exit(0);
			}

			AppLog::error("invalid download data", __FILE__, __LINE__, __FUNCTION__);
			return false;
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
		public function sendApiResponse(int $code, string $message = "", string $data = ""): void {
			if(0 != ob_get_level() && !ob_end_clean()) {
				AppLog::error("failed to clear output buffer before sending API response (requested action = \"" . $this->currentRequest()->action() . "\"; response = \"$code $message\")", __FILE__, __LINE__, __FUNCTION__);
			}

			echo "$code" . (empty($message) ? "" : " $message") . "\n$data";
			exit(0);
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
		 * LibEquit\Request object). In the case of some application events and possibly some plugin events, the
		 * LibEquit\Request can be _null_ (it is up to the plugin to provide the appropriate request when it emits the
		 * event, it is not automatically provided by emitEvent()). Plugins that emit events are very strongly
		 * recommended to provide a LibEquit\Request object wherever possible.
		 *
		 * Callbacks stack up, so if you add the same callback more than once, it will be called more than once every
		 * time the event occurs.
		 *
		 * @param $event string is the event to connect to.
		 * @param $callback callable is the function or method to call when the event occurs.
		 *
		 * @return bool _true_ if the callback was connected to the event, _false_ otherwise.
		 */
		public function connect(string $event, callable $callback): bool {
			if(!is_string($event)) {
				AppLog::error("invalid event", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!is_callable($callback, true)) {
				AppLog::error("invalid callback", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$event = strtolower($event);

			if(!isset($this->m_eventCallbacks[$event])) {
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
		public function disconnect(string $event, callable $callback): bool {
			if(!is_string($event)) {
				AppLog::error("invalid event", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!is_callable($callback, true)) {
				AppLog::error("invalid callback", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$event = strtolower($event);

			if(isset($this->m_eventCallbacks[$event])) {
				$myCallbacks = [];

				foreach($this->m_eventCallbacks[$event] as $myCallback) {
					if($callback == $myCallback) {
						continue;
					}

					$myCallbacks[] = $myCallback;
				}

				$this->m_eventCallbacks[$event] = $myCallbacks;
			}

			return true;
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
		 * @param $eventArgs ...mixed Zero or more arguments to provide to the event callbacks.
		 *
		 * @return bool _true_ if the event was emitted successfully, _false_ otherwise. An event that is valid but
		 * happens to have no connected callbacks returns _true_.
		 */
		public function emitEvent(string $event, ... $eventArgs): bool {
			if(!is_string($event)) {
				AppLog::error("invalid event", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$event = strtolower($event);

			if(isset($this->m_eventCallbacks[$event])) {
				foreach($this->m_eventCallbacks[$event] as $callback) {
					if(!is_callable($callback, false)) {
						AppLog::error("ignoring un-callable callback: " . stringify($callback), __FILE__, __LINE__, __FUNCTION__);
						continue;
					}

					$callback($event, ... $eventArgs);
				}
			}

			return true;
		}

		/** Push a request onto the request stack.
		 *
		 * This is a private internal method that maintains the request stack that is used to provide plugins with
		 * access to the current request. It should only be used by LibEquit\Application::handleRequest()
		 *
		 * @param $request Request The request to push onto the stack.
		 */
		protected function pushRequest(Request $request): void {
			array_push($this->m_requestStack, $request);
		}

		/** Pop a request from the request stack.
		 *
		 * This is a private internal method that maintains the request stack that is used to provide plugins with
		 * access to the current request. It should only be used by LibEquit\Application::handleRequest()
		 */
		protected function popRequest(): void {
			array_pop($this->m_requestStack);
		}

		/**
		 * Fetch the current language.
		 *
		 * @return string|null The current language, or @null if no translator is set or the translator has no language
		 * set.
		 */
		public function currentLanguage(): ?string {
			$translator = $this->translator();
			return ($translator ? $translator->language() : null);
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
		public function currentRequest(): ?Request {
			$l = count($this->m_requestStack);

			if(0 < $l) {
				return $this->m_requestStack[$l - 1];
			}

			return null;
		}

		/** Fetch the original request submitted by the user.
		 *
		 * This method fetches the original request received from the user. It is just a convenience synonym for
		 * LibEquit\Request::originalUserRequest().
		 *
		 * @see-also currentRequest()
		 *
		 * @return Request The user's original request.
		 */
		public function originalRequest(): Request {
			return Request::originalUserRequest();
		}

		/** Handle a request.
		 *
		 * This method submits a request to the application for processing. Processing of the request starts
		 * immediately and any current request is held until it finishes. Plugins can use this method to submit
		 * requests to perform actions without having to know about what other plugins are available to the
		 * application.
		 *
		 * @param $request Request The request to handle.
		 *
		 * @return bool _true_ if the request was accepted, _false_ otherwise. A request for an action for which there
		 *     is no registered plugin will result in a redirect to the home page, and will return _false_.
		 */
		public function handleRequest(Request $request): bool {
			if(!($request instanceof Request)) {
				AppLog::error("invalid request", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$this->pushRequest($request);
			$this->emitEvent("application.handlerequest.requestreceived", $request);
			$action = mb_convert_case($request->action(), MB_CASE_LOWER, "UTF-8");

			if(empty($action) || "home" == $action) {
				$this->page()->addMainElement(new HtmlLiteral("<div id=\"" . html(constant("app.uid")) . "-homepage-container\" class=\"section container\">"));
				$this->emitEvent("home.creatingtopsection");
				$this->emitEvent("home.creatingmiddlesection");
				$this->emitEvent("home.creatingbottomsection");
				$this->page()->addMainElement(new HtmlLiteral("</div> <!-- " . html(constant("app.uid")) . "-homepage-container -->"));
				$this->popRequest();
				return true;
			}

			$this->emitEvent("application.handlerequest.abouttofetchplugin");
			$plugin = $this->pluginForAction($action);

			if(!$plugin) {
				$this->emitEvent("application.handlerequest.failedtofetchplugin");
				$this->popRequest();
				return false;
			}

			$this->emitEvent("application.handlerequest.pluginfetched", $plugin);
			$this->emitEvent("application.handlerequest.abouttoexecuteplugin", $plugin);
			$ret = $plugin->handleRequest($request);
			$this->popRequest();
			return $ret;
		}

		/** Execute the application.
		 *
		 * Start execution of the application. This method will pass the original request from the user to
		 * _handleRequest()_ and return when processing of that request completes. This method should never be called,
		 * except from the script that is in use as the application bootstrap.
		 *
		 * This method is responsible for setting up the execution context for the request, including initialising the
		 * page. It emits some events that may be of interest to plugins.
		 *
		 * Once this method returns, the application is considered to have exited.
		 */
		public function exec(): void {
			if(0 > version_compare(PHP_VERSION, $this->minimumPhpVersion())) {
				$appName = $this->title();

				if(empty($appName)) {
					$appName = tr("This application");
				}

				AppLog::error("$appName needs PHP v" . $this->minimumPhpVersion() . " but v" . PHP_VERSION . " is installed", __FILE__, __LINE__, __FUNCTION__);
				trigger_error(tr("%1 is not able to run on this server.", __FILE__, __LINE__, $appName), E_USER_ERROR);
			}

			$this->m_isRunning = true;
			ob_start();
			$page = $this->page();

			$page->addScriptUrl("js/application.js");
			$page->addJavascript("Application.Private.baseUrl = \"" . Request::baseName() . "\";");

			$this->loadPlugins();
			$this->emitEvent("application.executionstarted");
			$this->handleRequest(Request::originalUserRequest());
			$this->emitEvent("application.executionfinished");
			$this->emitEvent("application.abouttooutputpage");
			ob_end_flush();
			$page->output();
			$this->emitEvent("application.pageoutputfinished");

			if(!empty($this->m_session["current_user"])) {
				$this->m_session["current_user"]["last_activity_time"] = time();
			}

			$this->m_isRunning = false;
		}
	}
}

namespace {

	require_once("includes/string.php");

	use Equit\Application;

	/** Convenience function to ease UI string translation.
	 *
	 * The idea is that strings to translate are passed to this function, which looks up the translation in a locale
	 * file. The file and line are provided for the purposes of disambiguation, should two identical source strings
	 * need to be translated differently in different contexts.
	 *
	 * Because translated strings may need to contain variable content - i.e. have the name of something inserted into
	 * them - and because the order of the inserted content in the string may vary between languages, indexed
	 * placeholders can be placed in the strings. The strings are then translated, with the translation placing the
	 * placeholders in a different order if required, so that the content can then be inserted into the string after
	 * translation in the correct order for the target language.
	 *
	 * If provided with additional _$args_ this function, will insert the content of these arguments into the translated
	 * string based on the placeholders it contains. It does this using the @link buildString() function.
	 * If you don't wish to have the tr() function do this automatically for you, simply call it without any additional
	 * arguments and you will receive the translated string with its placeholders all still present.
	 *
	 * @param $string string The string to translate.
	 * @param $file string|null _optional_ The file from which the string originates.
	 * @param $line int|null _optional_ The source code line from which the string originates.
	 * @param $args ...mixed _optional_ The values to insert in place of placeholders in the translated string.
	 *
	 * @return string The translated string.
	 */
	function tr(string $string, string $file = null, int $line = null, ... $args): string {
		$app = Application::instance();

		if(isset($app)) {
			$translator = $app->translator();

			if(isset($translator)) {
				$string = $translator->translate($string, $file, $line);
			}
		}

		if(1 > count($args)) {
			return $string;
		}

		return buildString($string, ... $args);
	}
}

