<?php

namespace Bead;

use TypeError;

/**
 * Abstract representation of an incoming HTTP request.
 *
 * The original request submitted by the user agent is always available using the static method `originalRequest()`. The
 * data submitted with the request can be retrieved using `urlParameter()`, `postData()` and `uploadedFile()` for,
 * respectively, URL parameters, POST data and uploaded files. For a subset of URL parameters or POST data use
 * `onlyUrlParameters()` and `onlyPostData()`, giving an array of keys to retrieve.
 */
class Request
{
    /** @var string The HTTP protocol. */
    public const HttpProtocol = "http";

    /** @var string The HTTPS protocol. */
    public const HttpsProtocol = "https";

    /** @var \Bead\Request|null The request parsed from the superglobals. */
    private static ?Request $s_originalRequest = null;

    /** @var array<string, string> The request's URL parameters. */
    private array $m_urlParams = [];

    /** @var array<string, array|string> The request's POST data. */
    private array $m_postData = [];

    /** @var array<string,UploadedFile> The files uploaded with the request. */
    private array $m_files = [];

    /** @var array<string, string> The request's HTTP headers. */
    private array $m_headers = [];

    /** @var string The full request URL. */
    private string $m_url;

    /** @var string The request's protocol. */
    private string $m_protocol;

    /** @var string The host part of the request URL. */
    private string $m_host;

    /** @var string The path part of the request URL. */
    private string $m_path;

    /** @var string The PathInfo part of the request URL. */
    private string $m_pathInfo = "";

    /** @var string The HTTP request method for the request. */
    private string $m_method = "";

    /**
     * Create a new Request.
     *
     * @param $action string|null The action the request is for.
     */
    private function __construct()
    {
        $scheme = (!empty($_SERVER["HTTPS"]) ? self::HttpsProtocol : self::HttpProtocol);
        $host = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "";

        $this->setProtocol($scheme);
        $this->setHost($host);
        $this->setPath("/");
        $this->setPathInfo("/");
        $this->setMethod("GET");

        $this->m_url = "{$scheme}://{$host}/";
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
     * The URL provided will be as it is in the incoming request. It is constructed from the HTTP headers and server
     * variables. It is therefore not 100% guaranteed to match the address in the user's browser - it's dependent on
     * the web server providing the information.
     *
     * @return string The request URL.
     */
    public function url(): string
    {
        return $this->m_url;
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
        return array_filter($this->m_urlParams, fn (string $key): bool => in_array(strtolower($key), $keys), ARRAY_FILTER_USE_KEY);
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
        } elseif (is_string($value) || is_array($value)) {
            $this->m_postData[$key] = $value;
        } else {
            throw new TypeError("POST data value required to be string, array or null");
        }
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
        return array_filter($this->m_postData, fn (string $key): bool => in_array(strtolower($key), $keys), ARRAY_FILTER_USE_KEY);
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
     * @param $file UploadedFile The file to set.
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
        $name = mb_strtolower($name, "UTF-8");
        return $this->m_headers[$name] ?? $this->m_headers[str_replace("-", "_", $name)] ?? null;
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
     * Fetch the original request submitted by the user agent.
     *
     * The request provided is parsed from the $_GET, $_POST, $_FILES and $_SERVER superglobals. The parsing happens
     * only once, on the first call - the request is then cached so subsequent calls are fast. The provided request
     * remains owned by the `Request` class and must not be modified by external code.
     *
     * The PathInfo is taken from the PATH_INFO member of tehe $_SERVER superglobal, if it's present. If it isn't, it
     * is computed as the portion of the path that is between the location of the running script and the query string or
     * fragment or the end of the URL, whichever occurs soonest. For example, if the request is for
     * "https://example.com/foo/bar/baz" and the script "index.php" from inside "/foo/" is running, the PathInfo will
     * be "/baz".
     *
     * @return Request The original request submitted to the server.
     */
    public static function originalRequest(): Request
    {
        if (is_null(Request::$s_originalRequest)) {
            $req = new Request();

            foreach ($_GET as $key => $value) {
                $key = mb_strtolower($key, "UTF-8");
                $req->setUrlParameter($key, $value);
            }

            foreach ($_POST as $key => $value) {
                $key = mb_strtolower($key, "UTF-8");
                $req->setPostData($key, $value);
            }

            $req->m_files = UploadedFile::allUploadedFiles();

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

            if (isset($_SERVER["PATH_INFO"])) {
                $pathInfo = $_SERVER["PATH_INFO"];
            } elseif (isset($_SERVER["SCRIPT_NAME"])) {
                // attempt to extract the path info from the URI if PATH_INFO is not provided
                $scriptPath = $_SERVER["SCRIPT_NAME"];

                // if the script name is part of the URI, remove that and the reaminder is the path info
                if (str_starts_with($_SERVER["REQUEST_URI"], $scriptPath)) {
                    $pathInfo = "/" . substr($_SERVER["REQUEST_URI"], strlen($scriptPath));
                } else {
                    // otherwise, if the script name is not part of the URI, remove the path to the script from the URI
                    $scriptPath = dirname($scriptPath);

                    if (str_starts_with($_SERVER["REQUEST_URI"], $scriptPath)) {
                        $pathInfo = "/" . substr($_SERVER["REQUEST_URI"], strlen($scriptPath));
                    } else {
                        $pathInfo = "/";
                    }
                }

                // strip out the query string if present
                if (false !== ($pos = strpos($pathInfo, "?"))) {
                    $pathInfo = substr($pathInfo, 0, $pos);
                }
            } else {
                // don't know how we can access the path info
                $pathInfo = "/";
            }

            if (!str_starts_with($pathInfo, "/")) {
                $pathInfo = "/{$pathInfo}";
            }

            $req->setPathInfo($pathInfo);
            $req->setMethod(strtoupper($_SERVER["REQUEST_METHOD"]));
            $req->m_url = "{$req->protocol()}://{$req->host()}{$_SERVER["REQUEST_URI"]}";
            Request::$s_originalRequest = $req;
        }

        return Request::$s_originalRequest;
    }
}
