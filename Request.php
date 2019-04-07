<?php

/**
 * Defines the LibEquit\Request class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/UploadedFile.php
 *
 * ### Changes
 * - (2017-05) Updated documentation. Migrated from array() to `[]` syntax.
 * - (2013-12-10) First version of this file.
 *
 * @file LibEquit\Request.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit;

require_once("includes/string.php");

/**
 * Abstract representation of a request made to the application.
 *
 * This class is the basis for everything done by the application. When the
 * application runs, its main loop fetches the user's original request from
 * this class and passes it to LibEquit\Application::handleRequest(). The plugin
 * selected to handle the request is based on the LibEquit\Request object's action. The
 * action is retrieved using the action() method and can be set using the
 * setAction() method. Actions will usually only be set by plugins that need
 * to create URLs for elements they place on the page that are intended to
 * enable the user to ask the application to do something, and the action they
 * set will be based on the actions they support. Otherwise, the action
 * property is generally only read not written.
 *
 * The class provides some static methods that give information about the
 * application, such as the base URL for the application and its path. The
 * original request submitted by the user agent is always available using
 * originalUserRequest() so that plugins handling requests can always check
 * what the user originally asked the application to do when handling any other
 * requests that may be submitted to the application. Similary, a request to
 * display the home page is always available from the home() method.
 *
 * The data submitted with the request can be retrieved using urlParameter(),
 * postData() and uploadedFile() for, respectively, URL parameters, POST
 * data and uploaded files. Plugins that are creating requests to be handled
 * can use the related setters setUrlParameter(), setPostData() and
 * setUploadedFile() to create the requests they need.
 *
 * It is recommended that an object of this class is used whenever you need to
 * construct a URL for an element being placed on the page. The url() and
 * rawUrl() methods will provide you with the URL you need once you have set
 * all the parameters on the LibEquit\Request object. The url() and rawUrl() methods
 * do not take account of any POST data or uploaded files in the request.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * @aio-api None
 * ### API Functions
 * This module does not provide an API.
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
 * @class LibEquit\Request
 * @author Darren Edale
 * @package libequit
 * @see UploadedFile, Application
 */
class Request {
	private static $s_originalUserRequest = null;
	private $m_urlParams = [];
	private $m_postData = [];
	private $m_files = [];

	/**
	 * Create a new Request.
	 *
	 * @param $action string _optional_ The action the request is for.
	 *
	 * All requests contain one special URL parameter, the action, which is the
	 * core "thing" that the request is asking the application to do. It is
	 * matched by the LibEquit\Application object against the actions supported by
	 * plugins when choosing what to do with the request. The action can be
	 * `null` (or omitted from the constructor) to create a request with no
	 * action. Such a request will not be handled by any plugins.
	 */
	public function __construct($action = null) {
		$this->setAction($action);
	}

	/**
	 * Provide a string representation of the request.
	 *
	 * At present, this method just returns the request URL. The URL provided
	 * will be %-encoded.
	 *
	 * @return string The request URL.
	 */
	public function __toString() {
		return $this->url();
	}

	/**
	 * Fetch the request URL.
	 *
	 * The URL provided will be %-encoded.
	 *
	 * @return string The request URL.
	 */
	public function url() {
		$url   = Request::baseUrl();
		$first = true;

		foreach($this->m_urlParams as $key => $value) {
			if(is_null($value)) {
				continue;
			}

			if($first) {
				$url   .= '?';
				$first = false;
			}
			else {
				$url .= '&';
			}

			$url .= urlencode($key) . '=' . urlencode($value);
		}

		return $url;
	}

	/**
	 * Fetch the request's raw URL.
	 *
	 * The URL provided will not be encoded in any way.
	 *
	 * @return string The request URL.
	 */
	public function rawUrl() {
		$url   = Request::baseUrl();
		$first = true;

		foreach($this->m_urlParams as $key => $value) {
			if(is_null($value)) {
				continue;
			}

			if($first) {
				$url   .= '?';
				$first = false;
			}
			else {
				$url .= '&';
			}

			$url .= $key . '=' . $value;
		}

		return $url;
	}

	/* $key and $value must be strings. $key will be converted to lower case.
	 * $value may also be NULL to unset the parameter. */
	/**
	 * Set a URL parameter in the request.
	 *
	 * @param $key string The key for the URL parameter.
	 * @param $value string The value for the URL parameter.
	 *
	 * The value parameter may be `null` to unset a parameter in the URL. After
	 * this is done, the parameter will no longer appear in the URL.
	 *
	 * URL parameter keys are not case sensitive. All keys are converted to
	 * lower-case for consistency. Updating a parameter that already exists
	 * using a version of the key that differs only in case will overwrite the
	 * existing parameter value.
	 *
	 * @return bool `true` if the URL parameter was added, `false` otherwise.
	 */
	public function setUrlParameter($key, $value) {
		if(!is_string($key)) {
			AppLog::error('invalid parameter key: ' . stringify($key), __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');

		if(is_numeric($value)) {
			$value = '' . $value;
		}

		/* cannot unset action parameter using this method */
		if(is_null($value)) {
			if(array_key_exists($key, $this->m_urlParams)) {
				unset($this->m_urlParams[$key]);
			}

			return true;
		}
		else if(is_string($value)) {
			$this->m_urlParams[$key] = $value;
			return true;
		}

		AppLog::error('invalid parameter value: ' . stringify($value), __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Set the value for some POST data.
	 *
	 * @param $key `string` The key for the POST data.
	 * @param $value `string` The value for the POST data.
	 *
	 * The value parameter may be `null` to unset a piece of POST data. After
	 * this is done, the parameter will no longer appear in the POST data.
	 *
	 * POST data keys are not case sensitive. All keys are converted to lower-
	 * case for consistency. Updating dome data that already exists using a
	 * version of the key that differs only in case will overwrite the existing
	 * value.
	 *
	 * @return bool `true` if the POST data was added, `false` otherwise.
	 */
	public function setPostData($key, $value) {
		if(!is_string($key)) {
			AppLog::error('invalid data key: ' . stringify($key), __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');

		if(is_null($value)) {
			if(array_key_exists($key, $this->m_postData)) {
				unset($this->m_postData[$key]);
			}

			return true;
		}
		else if(is_string($value) || is_array($value)) {
			$this->m_postData[$key] = $value;
			return true;
		}

		AppLog::error('invalid data value: ' . stringify($value), __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Check whether an URL parameter was provided with the request.
	 *
	 * @param $key `string` They key of the parameter to check.
	 *
	 * Keys are not case sensitive.
	 *
	 * @return bool `true` if the URL parameter was provided, `false` otherwise.
	 */
	public function hasUrlParameter($key) {
		if(!is_string($key)) {
			AppLog::error('invalid parameter key: ' . stringify($key), __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');
		return array_key_exists($key, $this->m_urlParams);
	}

	/**
	 * Fetch the value of a URL parameter.
	 *
	 * @param $key `string` They key of the value to fetch.
	 *
	 * Keys are not case sensitive.
	 *
	 * @return string The URL parameter value, or `null` if the parameter is
	 * not set.
	 */
	public function urlParameter($key) {
		if(!is_string($key)) {
			AppLog::error('invalid parameter key: ' . stringify($key), __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');

		if(array_key_exists($key, $this->m_urlParams)) {
			return $this->m_urlParams[$key];
		}

		return null;
	}

	/**
	 * Fetch all URL parameters.
	 *
	 * The URL parameters are provided as an associative array. All parameter
	 * keys are guaranteed to be all lower-case.
	 *
	 * @return array[string=>string] The URL parameters.
	 */
	public function allUrlParameters() {
		return $this->m_urlParams;
	}

	/**
	 * Check whether some POST data was provided with the request.
	 *
	 * @param $key `string` They key of the data to check.
	 *
	 * Keys are not case sensitive.
	 *
	 * @return bool `true` if the POST data was provided, `false` otherwise.
	 */
	public function hasPostData($key) {
		if(!is_string($key)) {
			AppLog::error('invalid data key: ' . stringify($key), __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');
		return array_key_exists($key, $this->m_postData);
	}

	/**
	 * Fetch the value of some POST data.
	 *
	 * @param $key `string` They key of the value to fetch.
	 *
	 * Keys are not case sensitive.
	 *
	 * @return string The POST data value, or `null` if the POST data with
	 * the key provided is not set.
	 */
	public function postData($key) {
		if(!is_string($key)) {
			AppLog::error('invalid data key: ' . stringify($key), __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');

		if(array_key_exists($key, $this->m_postData)) {
			return $this->m_postData[$key];
		}

		return null;
	}

	/**
	 * Fetch all the POST data.
	 *
	 * The POST data is provided as an associative array. All POST data keys are
	 * guaranteed to be all lower-case.
	 *
	 * @return array[string=>string] The URL parameters.
	 */
	public function allPostData() {
		return $this->m_postData;
	}

	/**
	 * Fetch the value of a URL parameter, or if it is absent the
	 * POST data.
	 *
	 * @param $name `string` They key of the value to fetch.
	 *
	 * Keys are not case sensitive.
	 *
	 * @return string The URL parameter, or the POST data value if the
	 * URL parameter is not set, or `null` if the neither is set.
	 */
	public function urlParameterOrPostData($name) {
		$ret = $this->urlParameter($name);

		if(is_null($ret)) {
			$ret = $this->postData($name);
		}

		return $ret;
	}


	/**
	 * Fetch the value of some POST data, or if it is absent the
	 * URL parameter.
	 *
	 * @param $name `string` They key of the value to fetch.
	 *
	 * Keys are not case sensitive.
	 *
	 * @return string The POST data value, or the URL parameter if the
	 * POST data is not set, or `null` if the neither is set.
	 */
	public function postDataOrUrlParameter($name) {
		$ret = $this->postData($name);

		if(is_null($ret)) {
			$ret = $this->urlParameter($name);
		}

		return $ret;
	}

	/**
	 * Fetch an uploaded file.
	 *
	 * @param $identifier `string` The identifier of the file to fetch.
	 *
	 * Uploaded file identifiers are not cas sensitive.
	 *
	 * @return UploadedFile The requested file, or _null_ if the file does not exist.
	 */
	public function uploadedFile($identifier) {
		if(!is_string($identifier)) {
			AppLog::error("invalid uploaded file identifier: " . stringify($identifier), __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$identifier = mb_convert_case($identifier, MB_CASE_LOWER, 'UTF-8');

		if(array_key_exists($identifier, $this->m_files)) {
			return $this->m_files[$identifier];
		}

		return null;
	}

	/**
	 * Set an uploaded file.
	 *
	 * @param $identifier `string` The identifier for the uploaded file.
	 * @param $file `UploadedFile` The file to set.
	 *
	 * Uploaded file identifiers are not case sensitive. All identifiers are
	 * converted to lower- case for consistency. Updating a file that already
	 * exists using a version of the identifier that differs only in case will
	 * discard and replace the existing file object.
	 *
	 * This method is of most use when the application is parsing the request
	 * sent by the user agent.
	 *
	 * @return bool `true` if the uploaded file was set, `false` otherwise.
	 */
	public function setUploadedFile($identifier, &$file) {
		if(!is_string($identifier)) {
			AppLog::error('invalid uploaded file identifier: ' . stringify($identifier), __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$identifier = mb_convert_case($identifier, MB_CASE_LOWER, 'UTF-8');

		if($file instanceof UploadedFile) {
			$this->m_files[$identifier] = $file;
			return true;
		}
		else if(is_null($file)) {
			if(array_key_exists($identifier, $this->m_files)) {
				unset($this->m_files[$identifier]);
			}

			return true;
		}

		AppLog::error('invalid uploaded file: ' . stringify($file), __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Set the request action.
	 *
	 * @param $action `string` The action to set.
	 *
	 * The action can be set to `null` to unset the action. Doing so will,
	 * however, make the request one that will not be passed to any plugins when
	 * submitted to LibEquit\Application::handleRequest().
	 *
	 * LibEquit\Request actions are case sensitive.
	 *
	 * @return bool `true` if the action was set, `false` otherwise.
	 */
	public function setAction($action) {
		return $this->setUrlParameter('action', $action);
	}

	/**
	 * Fetch the request action.
	 *
	 * @return string The action, or `null` if no action is set.
	 */
	public function action() {
		return (array_key_exists('action', $this->m_urlParams) ? $this->m_urlParams['action'] : null);
	}

	/**
	 * Fetch the application's base URL.
	 *
	 * The base URL is constructed based on the content of the $_SERVER superglobal. It can be useful when constructing
	 * URLs for page elements.
	 *
	 * @return string The base URL for the application.
	 */
	public static function baseUrl() {
		return 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
	}

	/**
	 * Fetch the path for the base URL.
	 *
	 * This method provides the path part of the base URL - the URL without the
	 * script name attached. This can be useful when constructing URLs for page
	 * elements that need to reference resources other than the main
	 * application. It contains the protocol.
	 *
	 * @return string The base URL path.
	 */
	public static function basePath() {
		return dirname(Request::baseUrl());
	}

	/**
	 * Fetch the path for the base URL.
	 *
	 * This method provides the script part of the base URL - the URL without
	 * the path prefix. This can be useful when you just need the name of the
	 * base script that is running.
	 *
	 * @return string The base URL path.
	 */
	public static function baseName() {
		return basename(Request::baseUrl());
	}

	/**
	 * Fetch the home request.
	 *
	 * This method provides a request object that is guaranteed to display the
	 * home page when provided to LibEquit\Application::handleRequest(). This method
	 * never fails, and it is safe to modify the provided request, doing so will
	 * not cause subsequent requests retrieved using this method to be corrupt.
	 *
	 * @return Request The home request.
	 */
	public static function home() {
		static $s_home = null;

		if(is_null($s_home)) {
			$s_home = new Request();
		}

		return clone $s_home;
	}

	/**
	 * Fetch the original request submitted by the user agent.
	 *
	 * The request provided is parsed from the $_GET, $_POST and $_FILES
	 * superglobals. The parsing happens only once, on the first call - the
	 * request is then cached so subsequent calls are fast. The provided
	 * request remains owned by the LibEquit\Request class and must not be modified
	 * by other code.
	 *
	 * @return Request A representation of the user's original request.
	 */
	public static function originalUserRequest(): Request {
		if(is_null(Request::$s_originalUserRequest)) {
			$req = new Request();

			foreach($_GET as $key => $value) {
				$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');
				$req->setUrlParameter($key, $value);
			}

			foreach($_POST as $key => $value) {
				$key = mb_convert_case($key, MB_CASE_LOWER, 'UTF-8');
				$req->setPostData($key, $value);
			}

			foreach($_FILES as $key => $value) {
				$f = UploadedFile::createFromFile($value['tmp_name'], $value['name']);
				$f->setMimeType($value['type']);
				$req->m_files[$key] = $f;
			}

			Request::$s_originalUserRequest = $req;
		}

		return Request::$s_originalUserRequest;
	}
}
