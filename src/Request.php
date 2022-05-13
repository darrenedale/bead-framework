<?php

/**
 * Defines the LibEquit\Request class.
 *
 * ### Changes
 * - (2022-05) Type hinting as far as PHP7.4 permits.
 *             Throws TypeError instead of using return values to indicate violation of parameter type constraints.
 * - (2017-05) Updated documentation. Migrated from array() to `[]` syntax.
 * - (2013-12-10) First version of this file.
 *
 * @file LibEquit\Request.php
 * @author Darren Edale
 * @version 1.2.0
 * @package libequit
 * @date Jan 2018
 */

namespace Equit;

use TypeError;

/**
 * Abstract representation of a request made to the application.
 *
 * This class is the basis for everything done by the application. When the application runs, its main loop fetches the
 * user's original request from this class and passes it to LibEquit\Application::handleRequest(). The plugin selected
 * to handle the request is based on the LibEquit\Request object's action. The action is retrieved using the action()
 * method and can be set using the setAction() method. Actions will usually only be set by plugins that need to create
 * URLs for elements they place on the page that are intended to enable the user to ask the application to do
 * something, and the action they set will be based on the actions they support. Otherwise, the action property is
 * generally only read not written.
 *
 * The class provides some static methods that give information about the application, such as the base URL for the
 * application and its path. The original request submitted by the user agent is always available using
 * originalUserRequest() so that plugins handling requests can always check what the user originally asked the
 * application to do when handling any other requests that may be submitted to the application. Similarly, a request to
 * display the home page is always available from the home() method.
 *
 * The data submitted with the request can be retrieved using urlParameter(), postData() and uploadedFile() for,
 * respectively, URL parameters, POST data and uploaded files. Plugins that are creating requests to be handled can use
 * the related setters setUrlParameter(), setPostData() and setUploadedFile() to create the requests they need.
 *
 * It is recommended that an object of this class is used whenever you need to construct a URL for an element being
 * placed on the page. The url() and rawUrl() methods will provide you with the URL you need once you have set all the
 * parameters on the LibEquit\Request object. The url() and rawUrl() methods do not take account of any POST data or
 * uploaded files in the request.
 *
 * ### Events
 * This module does not emit any events.
 *
 * @noconnections
 * ### Connections
 * This module does not connect to any events.
 *
 * @nosettings
 * ### Settings
 * This module does not read any settings.
 *
 * @nosession
 * ### Session Data
 * This module does not create a session context.
 *
 * @author Darren Edale
 * @package libequit
 * @see UploadedFile, WebApplication
 */
class Request
{
	public const HttpProtocol = "http";
	public const HttpsProtocol = "https";

	/** @var \Equit\Request|null The request parsed from the superglobals. */
	private static ?Request $s_originalUserRequest = null;

	/** @var array<string, string> The request's URL parameters. */
	private array $m_urlParams = [];

	/** @var array<string, array|string> The request's POST data. */
	private array $m_postData = [];

	/** @var array<string,\Equit\UploadedFile> The files uploaded with the request. */
	private array $m_files = [];

	/** @var array<string, string> The request's HTTP headers. */
	private array $m_headers = [];

	private string $m_protocol;
	private string $m_host;
	private string $m_path;

	/** @var string The URL path. */
	private string $m_pathInfo = "";

	private string $m_method = "";

	/**
	 * Create a new Request.
	 *
	 * @param $action string|null The action the request is for.
	 *
	 * All requests contain one special URL parameter, the action, which is the core "thing" that the request is asking 
	 * the application to do. It is matched by the Equit\Application object against the actions supported by plugins
	 * when choosing what to do with the request. The action can be `null` (or omitted from the constructor) to create a
	 * request with no action. Such a request will not be handled by any plugins.
	 */
	public function __construct(?string $action = null)
	{
		$this->setAction($action);
		$this->setProtocol(!empty($_SERVER["HTTPS"]) ? self::HttpsProtocol : self::HttpProtocol);
		$this->setHost($_SERVER["SERVER_NAME"] ?? "");
		$this->setPath("/");
		$this->setPathInfo("");
		$this->setMethod("GET");
	}

	/**
	 * Provide a string representation of the request.
	 *
	 * At present, this method just returns the request URL. The URL provided
	 * will be %-encoded.
	 *
	 * @return string The request URL.
	 */
	public function __toString(): string
	{
		return $this->url();
	}

	/**
	 * Fetch the request URL.
	 *
	 * The URL provided will be %-encoded.
	 *
	 * @return string The request URL.
	 */
	public function url(): string
	{
		return "{$this->protocol()}://{$this->host()}" . urlencode($this->path()) . urlencode($this->pathInfo()) . "{$this->encodedQueryString()}";
	}

	/**
	 * Fetch the request's raw URL.
	 *
	 * The URL provided will not be encoded in any way.
	 *
	 * @return string The request URL.
	 */
	public function rawUrl(): string
	{
		return "{$this->protocol()}://{$this->host()}{$this->path()}{$this->pathInfo()}{$this->queryString()}";
	}

	/**
	 * Fetch the request protocol.
	 *
	 * @return string The protocol.
	 */
	public function protocol(): string
	{
		return $this->m_protocol;
	}

	/**
	 * Set the request protocol.
	 *
	 * The protocol should be one of the class protocol constants - HTTP or HTTPS.
	 *
	 * @param string $protocol The protocol.
	 */
	public function setProtocol(string $protocol): void
	{
		$this->m_protocol = $protocol;
	}

	/**
	 * Fetch the request host.
	 *
	 * @return string The host.
	 */
	public function host(): string
	{
		return $this->m_host;
	}

	/**
	 * Set the request host.
	 *
	 * The host should be a valid hostname or IP.
	 *
	 * @param string $host The host.
	 */
	public function setHost(string $host): void
	{
		$this->m_host = $host;
	}

	/**
	 * Fetch the request method.
	 *
	 * @return string The method.
	 */
	public function method(): string
	{
		return $this->m_method;
	}

	/**
	 * Set the request method.
	 *
	 * The method should be one of the supported HTTP methods. It is not case sensitive, it will be converted to all
	 * upper-case when set.
	 *
	 * @param string $method The method.
	 */
	public function setMethod(string $method): void
	{
		$this->m_method = strtoupper($method);
	}

	/**
	 * Fetch the request path.
	 *
	 * The path is the part of the request URL between the host and the query string/fragment/end of the URL.
	 *
	 * @return string The path.
	 */
	public function path(): string
	{
		return $this->m_path;
	}

	/**
	 * Set the request path.
	 *
	 * @param string $path The path.
	 */
	public function setPath(string $path): void
	{
		$this->m_path = $path;
	}

	/**
	 * Fetch the request path.
	 *
	 * The path is the part of the request URL between the host and the query string/fragment/end of the URL.
	 *
	 * @return string The path.
	 */
	public function pathInfo(): string
	{
		return $this->m_pathInfo;
	}

	/**
	 * Set the request path.
	 *
	 * @param string $path The path.
	 */
	public function setPathInfo(string $path): void
	{
		$this->m_pathInfo = $path;
	}

	/**
	 * Fetch the query string.
	 *
	 * The query string returned is %-encoded.
	 *
	 * @return string The %-encoded query string.
	 */
	public function encodedQueryString(): string
	{
		$query = "";

		foreach ($this->m_urlParams as $key => $value) {
			if (is_null($value)) {
				continue;
			}

			if (empty($query)) {
				$query .= "?";
			} else {
				$query .= "&";
			}

			$query .= urlencode($key) . "=" . urlencode($value);
		}

		return $query;
	}

	/**
	 * Fetch the query string.
	 *
	 * The plain-text query string.
	 *
	 * @return string The %-encoded query string.
	 */
	public function queryString(): string
	{
		$query = "";

		foreach ($this->m_urlParams as $key => $value) {
			if (is_null($value)) {
				continue;
			}

			if (empty($query)) {
				$query .= "?";
			} else {
				$query .= "&";
			}

			$query .= "{$key}={$value}";
		}

		return $query;
	}

	/**
	 * Set a URL parameter in the request.
	 *
	 * The value parameter may be `null` to unset a parameter in the URL. After this is done, the parameter will no
	 * longer appear in the URL.
	 *
	 * URL parameter keys are not case-sensitive. All keys are converted to lower-case for consistency. Updating a
	 * parameter that already exists using a version of the key that differs only in case will overwrite the existing
	 * parameter value.
	 *
	 * @param $key string The key for the URL parameter.
	 * @param $value string|null The value for the URL parameter.
	 */
	public function setUrlParameter(string $key, ?string $value): void
	{
		$key = mb_strtolower($key, "UTF-8");

		if (is_null($value)) {
			unset($this->m_urlParams[$key]);
		} else {
			$this->m_urlParams[$key] = $value;
		}
	}

	/**
	 * Set the value for some POST data.
	 *
	 * The value parameter may be `null` to unset a piece of POST data. After this is done, the parameter will no
	 * longer appear in the POST data.
	 *
	 * POST data keys are not case-sensitive. All keys are converted to lower-case for consistency. Updating some data
	 * that already exists using a version of the key that differs only in case will overwrite the existing value.
	 *
	 * @param $key string The key for the POST data.
	 * @param $value string|array|null The value for the POST data.
	 *
	 * @throws TypeError if `$value` is not string, array or null.
	 */
	public function setPostData(string $key, $value): void
	{
		$key = mb_strtolower($key, "UTF-8");

		if (is_null($value)) {
			unset($this->m_postData[$key]);
		} else if (is_string($value) || is_array($value)) {
			$this->m_postData[$key] = $value;
		} else {
            throw new TypeError("POST data value required to be string, array or null");
        }
	}

	/**
	 * Check whether a URL parameter was provided with the request.
	 *
	 * Keys are not case-sensitive.
	 *
	 * @param $key string They key of the parameter to check.
	 *
	 * @return bool `true` if the URL parameter was provided, `false` otherwise.
	 */
	public function hasUrlParameter(string $key): bool
	{
		$key = mb_strtolower($key, 'UTF-8');
		return array_key_exists($key, $this->m_urlParams);
	}

	/**
	 * Fetch the value of a URL parameter.
	 *
	 * Keys are not case-sensitive.
	 *
	 * @param $key string They key of the value to fetch.
	 *
	 * @return string|null The URL parameter value, or `null` if the parameter is not set.
	 */
	public function urlParameter(string $key): ?string
	{
		$key = mb_strtolower($key, "UTF-8");
		return $this->m_urlParams[$key] ?? null;
	}

	/**
	 * Fetch a subset of the URL parameters.
	 *
	 * The URL parameters are provided as an associative array. All parameter keys are guaranteed to be all lower-case.
     * Only those URL parameters whose name matches one of the provided keys are provided. Any keys that don't
     * identify URL parameters will be absent from the returned array.
	 *
	 * @return array<string, string> The URL parameters.
	 */
	public function onlyUrlParameters(array $keys): array
	{
		return array_filter($this->m_urlParams, fn(string $key): bool => in_array(strtolower($key), $keys), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Fetch all URL parameters.
	 *
	 * The URL parameters are provided as an associative array. All parameter
	 * keys are guaranteed to be all lower-case.
	 *
	 * @return array<string, string> The URL parameters.
	 */
	public function allUrlParameters(): array
	{
		return $this->m_urlParams;
	}

	/**
	 * Check whether some POST data was provided with the request.
	 *
	 * Keys are not case-sensitive.
	 *
	 * @param $key string The key of the data to check.
	 *
	 * @return bool `true` if the POST data was provided, `false` otherwise.
	 */
	public function hasPostData(string $key): bool
	{
		$key = mb_strtolower($key, 'UTF-8');
		return array_key_exists($key, $this->m_postData);
	}

	/**
	 * Fetch the value of some POST data.
	 *
	 * Keys are not case-sensitive.
	 *
	 * @param $key string They key of the value to fetch.
	 *
	 * @return string|array|null The POST data value, or `null` if the POST data with the key provided is not set.
	 */
	public function postData(string $key)
	{
		$key = mb_strtolower($key, 'UTF-8');

		if (array_key_exists($key, $this->m_postData)) {
			return $this->m_postData[$key];
		}

		return null;
	}

    /**
     * Fetch a subset of the POST data.
     *
     * The POST data are provided as an associative array. All keys are guaranteed to be all lower-case. Only those URL
     * parameters whose name matches one of the provided keys are provided. Any keys that don't identify URL parameters
     * will be absent from the returned array.
     *
     * @return array<string, string> The requested POST data.
     */
    public function onlyPostData(array $keys): array
    {
        return array_filter($this->m_postData, fn(string $key): bool => in_array(strtolower($key), $keys), ARRAY_FILTER_USE_KEY);
    }

	/**
	 * Fetch all the POST data.
	 *
	 * The POST data is provided as an associative array. All POST data keys are guaranteed to be all lower-case.
	 *
	 * @return array<string, string> The POST data.
	 */
	public function allPostData(): array
	{
		return $this->m_postData;
	}

	/**
	 * Fetch the value of a URL parameter, or if it is absent the POST data.
	 *
	 * The URL parameter takes precedence.
	 * 
	 * Keys are not case-sensitive.
	 *
	 * @param $key string They key of the value to fetch.
	 *
	 * @return string|array|null The value or `null` if neither the URL parameter nor POST data is set.
	 */
	public function urlParameterOrPostData(string $key)
	{
		return $this->urlParameter($key) ?? $this->postData($key);
	}


	/**
	 * Fetch the value of some POST data, or if it is absent the URL parameter.
	 *
	 * The POST data takes precedence.
	 *
	 * Keys are not case-sensitive.
	 *
	 * @param $key string They key of the value to fetch.
	 *
	 * @return string|array|null The value or `null` if neither the POST data nor the URL parameter is set.
	 */
	public function postDataOrUrlParameter(string $key)
	{
		return $this->postData($key) ?? $this->urlParameter($key);
	}

	/**
	 * Fetch an uploaded file.
	 *
	 * Uploaded file identifiers are not case-sensitive.
	 *
	 * @param $identifier string The identifier of the file to fetch.
	 *
	 * @return UploadedFile|null The requested file, or `null` if the file does not exist.
	 */
	public function uploadedFile(string $identifier): ?UploadedFile
	{
		$identifier = mb_strtolower($identifier, "UTF-8");

		if (array_key_exists($identifier, $this->m_files)) {
			return $this->m_files[$identifier];
		}

		return null;
	}

	/**
	 * Set an uploaded file.
	 *
	 * @param $identifier string The identifier for the uploaded file.
	 * @param $file `UploadedFile` The file to set.
	 *
	 * Uploaded file identifiers are not case-sensitive. All identifiers are
	 * converted to lower- case for consistency. Updating a file that already
	 * exists using a version of the identifier that differs only in case will
	 * discard and replace the existing file object.
	 *
	 * This method is of most use when the application is parsing the request
	 * sent by the user agent.
	 */
	public function setUploadedFile(string $identifier, ?UploadedFile $file): void
	{
		$identifier = mb_strtolower($identifier, "UTF-8");

		if (!isset($file)) {
			unset($this->m_files[$identifier]);
		} else {
			$this->m_files[$identifier] = $file;
		}
	}

	/**
	 * Fetch the value for an HTTP header.
	 *
	 * @param string $name The header requested.
	 *
	 * @return string|null The header value, or `null` if the header is not set.
	 */
	public function header(string $name): ?string
	{
		return $this->m_headers[$name] ?? $this->m_headers[str_replace("-", "_", $name)] ?? null;
	}

	/**
	 * Set the request action.
	 *
	 * The action can be set to `null` to unset the action. Doing so will, however, make the request one that will not
	 * be passed to any plugins when submitted to LibEquit\Application::handleRequest().
	 *
	 * Request actions are case-sensitive.
	 *
	 * @param $action string|null The action to set.
	 * @deprecated Use the framework's routing mechanism instead.
	 */
	public function setAction(?string $action): void
	{
		$this->setUrlParameter("action", $action);
	}

	/**
	 * Fetch the request action.
	 *
	 * @return string The action, or `null` if no action is set.
	 * @deprecated Use the framework's routing mechanism instead.
	 */
	public function action(): ?string
	{
		return $this->m_urlParams["action"] ?? null;
	}

	/**
	 * Determine whether the request was submitted as an AJAX request.
	 *
	 * This depends on a specific HTTP header being set to a specific value, which many frameworks provide.
	 *
	 * @return bool `true` if the request is AJAX, `false` if not.
	 */
	public function isAjax(): bool
	{
		// FE frameworks need to set this header. equit.js does so, as do many popular frameworks
		return "XMLHttpRequest" == $this->header('x_requested_with');
	}

	/**
	 * Fetch the home request.
	 *
	 * This method provides a request object that is guaranteed to display the home page when provided to
	 * WebApplication::handleRequest(). This method never fails, and it is safe to modify the provided request, doing so
	 * will not cause subsequent requests retrieved using this method to be corrupt.
	 *
	 * @return Request The home request.
	 */
	public static function home(): Request
	{
		return new Request();
	}

	/**
	 * Fetch the original request submitted by the user agent.
	 *
	 * The request provided is parsed from the $_GET, $_POST, $_FILES and $_SERVER superglobals. The parsing happens
	 * only once, on the first call - the request is then cached so subsequent calls are fast. The provided request
	 * remains owned by the `Request` class and must not be modified by external code.
	 *
	 * @return Request The original request submitted to the server.
	 */
	public static function originalRequest(): Request
	{
		if (is_null(Request::$s_originalUserRequest)) {
			$req = new Request();

			foreach ($_GET as $key => $value) {
				$key = mb_strtolower($key, "UTF-8");
				$req->setUrlParameter($key, $value);
			}

			foreach ($_POST as $key => $value) {
				$key = mb_strtolower($key, "UTF-8");
				$req->setPostData($key, $value);
			}

			foreach ($_FILES as $key => $value) {
				$file = UploadedFile::createFromFile($value["tmp_name"], $value["name"]);
				$file->setMimeType($value["type"]);
				$req->m_files[$key] = $file;
			}

			foreach ($_SERVER as $key => $value) {
				if ("HTTP_" === substr($key, 0, 5)) {
					$req->m_headers[mb_strtolower(substr($key, 5), "UTF-8")] = $value;
				} else {
					if (in_array($key, ["CONTENT_TYPE", "CONTENT_LENGTH", "CONTENT_MD5",])) {
						$req->m_headers[strtolower($key)] = $value;
					}
				}
			}

			$path = $_SERVER["SCRIPT_NAME"] ?? null;

			if (!isset($path)) {
				$path = "/";
			} else {
				if (!str_starts_with($path, "/")) {
					$path = "/{$path}";
				}

				$path = dirname($path);
			}

			$req->setPath($path);

			$pathInfo = $_SERVER["PATH_INFO"] ?? "";

			if (!str_starts_with($pathInfo, "/")) {
				$pathInfo = "/{$pathInfo}";
			}

			$req->setPathInfo($pathInfo);
			$req->setMethod(strtoupper($_SERVER["REQUEST_METHOD"]));
			Request::$s_originalUserRequest = $req;
		}

		return Request::$s_originalUserRequest;
	}
}
