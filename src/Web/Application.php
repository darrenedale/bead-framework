<?php

namespace Bead\Web;

use Bead\Contracts\Web\Request as RequestContract;
use Bead\Contracts\Web\RequestPostprocessor;
use Bead\Contracts\Web\RequestPreprocessor;
use Bead\Contracts\Web\Response;
use Bead\Contracts\Web\Router as RouterContract;
use Bead\Core\Application as CoreApplication;
use Bead\Exceptions\Http\NotFoundException;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\InvalidRoutesDirectoryException;
use Bead\Exceptions\InvalidRoutesFileException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Exceptions\Session\ExpiredSessionIdUsedException;
use Bead\Exceptions\Session\InvalidSessionHandlerException;
use Bead\Exceptions\Session\SessionException;
use Bead\Exceptions\Session\SessionExpiredException;
use Bead\Exceptions\Session\SessionNotFoundException;
use Bead\Exceptions\UnroutableRequestException;
use Bead\Facades\Session as SessionFacade;
use Bead\Session\DataAccessor as SessionDataAccessor;
use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SplFileInfo;
use UnexpectedValueException;

use function Bead\Helpers\I18n\tr;
use function Bead\Helpers\Str\random;

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
 * ## Requests
 * The request being handled can always be retrieved using the `request()` method. This retrieves the original HTTP
 * request that was submitted by the user agent (in other words the request `exec()` provided to `handleRequest()`).
 *
 * ## Inter-module communication
 * A simple inter-object communication mechanism is implemented by the Application class. This mechanism is based
 * on the concept of named events being emitted and objects subscribing to those events. Emitted events can provide
 * additional arguments that provide more details of the event (for example an event that fires when a particular type
 * of search has been executed might provide the search terms and result set as additional arguments).
 *
 * Events are emitted by calling the `emitEvent()` method. Subscriptions to events are achieved by calling the
 * `connect()` method. Subscriptions can be unsubscribed by calling `disconnect()`. Events do not need to be registered
 * or defined before they are emitted - it is sufficient just to call `emitEvent()` in order to emit an event. Any code
 * can emit events.
 *
 * Emitters of events should take care to document the events they emit and the arguments that are provided with them,
 * and should strive to keep the signatures of their events stable (API stability) and the names of their events
 * distinct to avoid event naming clashes between different emitters.
 *
 * ## Session management
 * The `WebApplication` class can be used to manage session data in a way that all-but guarantees clashes between the
 * bead app and other applications running on the same domain are avoided. It implements a mechanism that is very simple
 * to use by hiding the complexities of keeping session data distinct behind simple, unique "context" strings. See the
 * `sessionData()` method for details of how this works.
 *
 * ### Events
 * Web application emit the following events.
 *
 * - `application.executionstarted`
 *   Emitted when `exec()` starts actual execution (just before it calls `handleRequest()`).
 *
 * - `application.handlerequest.requestreceived($request)`
 *   Emitted when `handleRequest()` receives a request to process.
 *
 *   `$request` `Request` The request that was received.
 *
 * - `application.handlerequest.preprocessing($request)`
 *   Emitted when `handleRequest()` is about to begin passing the request through the configured pre-processors.
 *
 *   `$request` `Request` The request that is about to be pre-processed.
 *
 * - `application.handlerequest.preprocessed($request)`
 *   Emitted when `handleRequest()` has completed pre-processing the request.
 *
 *   `$request` `Request` The request that was pre-processed.
 *
 * - `application.handlerequest.routing(Request $request)`
 *   Emitted when `handleRequest()` is about to match the incoming Request to a route using the application's router.
 *
 *   `$request` `Request` The request that is about to be routed.
 *
 * - `application.handlerequest.routed(Request $request)`
 *   Emitted when `handleRequest()` has successfully matched and routed the incoming `Request` to a route using the
 *   application's router.
 *
 *   `$request` `Request` The request that was routed.
 *
 * - `application.handlerequest.postprocessing($request)`
 *   Emitted when `handleRequest()` is about to begin passing the request through the configured post-processors.
 *
 *   `$request` `Request` The request that is about to be pre-processed.
 *
 * - `application.handlerequest.postprocessed($request)`
 *   Emitted when `handleRequest()` has completed post-processing the request.
 *
 *   `$request` `Request` The request that was pre-processed.
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
 */
class Application extends CoreApplication
{
    /** @var string The context name for this class's session data. */
    public const SessionDataContext = "application";

    /** @var RequestPreprocessor|RequestPostprocessor[] The request pre-processors that have been added. */
    private array $m_requestProcessors = [];

    /** Application class's session data array. */
    protected ?SessionDataAccessor $m_session = null;

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
     *
     * @throws RuntimeException if an Application instance has already been created.
     * @throws ServiceAlreadyBoundException if an a service binder attempts to bind an implementation to a service that
     * is already bound.
     * @throws SessionException if the session can't be initialised
     * @throws LogicException if you've already initialised the session outside the Application
     */
    public function __construct(string $appRoot)
    {
        parent::__construct($appRoot);

        $this->initialiseSession();
        /** @psalm-suppress MissingThrowsDocblock $context is not empty */
        $this->m_session = $this->sessionData(self::SessionDataContext);
        $this->setRouter(new Router());
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
     * Initialise the application session data.
     *
     * @throws RuntimeException if the CSRF token needs to be refereshed but fails
     * @throws LogicException if the session has already been started
     * @throws SessionException If the expected internal data is not found in the session.
     * @throws ExpiredSessionIdUsedException if the session ID has timed out
     * @throws SessionExpiredException if the current session has expired
     * @throws SessionNotFoundException If the ID provided does not identify an existing session.
     * @throws InvalidSessionHandlerException if the session handler specified in the configuration file is not
     * recognised.
     */
    private function initialiseSession(): void
    {
        SessionFacade::start();
        // ensure the CSRF token is generated as soon as the session is started
        $this->csrf();
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
                throw new InvalidRoutesDirectoryException($this->routesDirectory(), "Routes directory \"{$this->routesDirectory()}\" is not a directory.");
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
                    /** @psalm-suppress UnusedVariable $app and $router are made available to the included route file */
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
     * Send a response to the client.
     *
     * @param Response $response The response to send.
     *
     * @throws RuntimeException if the output buffer was not empty and could not be cleaned.
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
     * Bead\Request::originalRequest().
     *
     * @see-also currentRequest()
     *
     * @return RequestContract The user's original request.
     */
    public function request(): RequestContract
    {
        return Request::capture();
    }

    /**
     * Fetch the current CSRF token.
     *
     * @return string The token.
     *
     * @throws RuntimeException if the token needs to be refreshed but cryptographically-secure random bytes cannot be
     * generated.
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
     *
     * @throws RuntimeException if cryptographically-secure random bytes cannot be generated.
     */
    public function regenerateCsrf(): void
    {
        SessionFacade::set("csrf-token", random(64));
    }

    /**
     * Load the additional preprocessors configured in the app config.
     *
     * The default implementation loads all preprocessors listed in app.preprocessors. Reimplement this if you need more
     * control over which preprocessors are loaded.
     *
     * @throws InvalidConfigurationException if the list of preprocessors is not valid.
     */
    protected function loadRequestProcessors(): void
    {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;
        $preprocessors = $this->config("app.processors", []);

        if (!is_array($preprocessors)) {
            throw new InvalidConfigurationException("app.processors", "Expected array of request processor classes.");
        }

        foreach ($preprocessors as $preprocessor) {
            if (!is_string($preprocessor)) {
                throw new InvalidConfigurationException("app.processors", "Expected valid request processor name, found " . gettype($preprocessor));
            }

            try {
                $preprocessor = $this->instantiateRequestProcessor($preprocessor);
            } catch (RuntimeException) {
                throw new InvalidConfigurationException("app.processors", "Expected valid preprocessor, found \"{$preprocessor}\"");
            }

            $this->m_requestProcessors[] = $preprocessor;
        }
    }

    /** @throws RuntimeException if the preprocessor does not exist or can't be instantiated. */
    protected function instantiateRequestProcessor(string $processor): RequestPreprocessor|RequestPostprocessor
    {
        if (!class_exists($processor)) {
            throw new RuntimeException("Processor class {$processor} does not exist.");
        }

        if (!is_subclass_of($processor, RequestPreprocessor::class) && !is_subclass_of($processor, RequestPostprocessor::class)) {
            throw new RuntimeException("Class {$processor} does not implement RequestPreprocessor or RequestPostprocessor.");
        }

        return new $processor();
    }

    /**
     * Feeds the request to the preprocessors.
     *
     * If any preprocessor returns a response, that response is returned and any following preprocessors are ignored.
     * If any preprocessor throws, that exception is used to determine the response and any following preprocessors are
     * ignored.
     *
     * @return Response|null The Response provided by the first preprocessor that returns one, or null if none of them
     * return a Response.
     */
    protected function preprocessRequest(RequestContract $request): ?Response
    {
        foreach ($this->m_requestProcessors as $preprocessor) {
            if (!$preprocessor instanceof RequestPreprocessor) {
                continue;
            }

            $response = $preprocessor->preprocessRequest($request);

            if (null !== $response) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Feeds the request to the postprocessors.
     *
     * If any postprocessor returns a response, that response is returned and any following postprocessor are ignored.
     * If any postprocessor throws, that exception is used to determine the response and any following postprocessor are
     * ignored.
     *
     * @return Response|null The Response provided by the first postprocessor that returns one, or null if none of them
     * return a Response.
     */
    protected function postprocessRequest(RequestContract $request, Response $response): ?Response
    {
        foreach ($this->m_requestProcessors as $postprocessor) {
            if (!$postprocessor instanceof RequestPostprocessor) {
                continue;
            }

            $replacementResponse = $postprocessor->postprocessRequest($request, $response);

            if (null !== $replacementResponse) {
                return $replacementResponse;
            }
        }

        return null;
    }

    /**
     * Handle a request.
     *
     * This method submits a request to the application for processing. Processing of the request starts immediately and
     * any current request is held until it finishes.
     *
     * @param $request RequestContract The request to handle.
     *
     * @return Response A Response to send to the client.
     * @throws InvalidConfigurationException if an invalid set of preprocessors is found
     * @throws NotFoundException if the request can't be routed
     */
    public function handleRequest(RequestContract $request): Response
    {
        $this->emitEvent("application.handlerequest.requestreceived", $request);

        try {
            $this->emitEvent("application.handlerequest.preprocessing", $request);
            $response = $this->preprocessRequest($request);
            $this->emitEvent("application.handlerequest.preprocessed", $request);

            if (null !== $response) {
                return $response;
            }

            $this->emitEvent("application.handlerequest.routing", $request);
            $response = $this->router()->route($request);
            $this->emitEvent("application.handlerequest.routed", $request);

            $this->emitEvent("application.handlerequest.postprocessing", $request);
            $postResponse = $this->postprocessRequest($request, $response);
            $this->emitEvent("application.handlerequest.postprocessed", $request);

            if (null !== $postResponse) {
                return $postResponse;
            }
        } catch (UnroutableRequestException $err) {
            throw new NotFoundException($request, tr("The requested page could not be found"), previous: $err);
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
     * Once this method returns, the application is considered to have exited.
     *
     * @return int `self::ErrOk` on success, some other value on failure.
     * @throws InvalidRoutesDirectoryException
     * @throws InvalidRoutesFileException
     * @throws RuntimeException
     * @throws NotFoundException
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
        $this->loadRequestProcessors();
        $this->loadRoutes();

        $this->emitEvent("application.executionstarted");
        $response = $this->handleRequest(Request::capture());
        $this->emitEvent("application.executionfinished");

        $this->emitEvent("application.sendingresponse");
        $this->sendResponse($response);
        $this->emitEvent("application.responsesent");

        if (!empty($this->m_session["current_user"])) {
            $this->m_session["current_user"]["last_activity_time"] = time();
        }

        $this->m_isRunning = false;
        return self::ExitOk;
    }
}
