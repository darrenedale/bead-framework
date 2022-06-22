<?php

namespace Equit;

use DateTime;
use Exception;
use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use StdClass;

/**
 * Provides an interface between application objects and data entities - it links the database to the objects used in
 * the application.
 *
 * Mappings between database tables and application objects are created by calling _addEntityMapping()_. This tells the
 * controller how to read data from tables in the database and insert it into application objects. These can then be
 * fetched using the _find()_, _findByField()_ and _findByFields()_ methods.
 *
 * It is based on PDO, so you can use it as a drop-in replacement for PDO and start using the ORM features as necessary.
 *
 * A few utility methods are also provided to ease creation of SQL, primarily for escaping wildcards and translating
 * between different types of wildcards.
 *
 * The class does not yet handle relationships between entities.
 *
 * ### Events
 * This module does not emit any events.
 *
 * ### Connections
 * This module does not connect to any events.
 *
 * ### Settings
 * This module does not read any settings.
 *
 * ### Session Data
 * This module does not create a session context.
 *
 * @class DataController
 * @author Darren Edale
 * @package bead-framework
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class DatabaseConnection extends PDO
{
	/**
	 * Create a new instance of a data controller.
	 *
	 * The username and password are optional as some database back ends do not support authentication and some
	 * databases will not require it.
	 *
	 * The constructor contains exception handling code. If an exception occurs a fatal application error is triggered.
	 *
	 * @param $resource string The database resource (usually a database name or file path).
	 * @param $userName string _optional_ The user name to use when authenticating with the database.
	 * @param $password string _optional_ The password to use when authenticating with the database.
	 */
	public function __construct(string $resource, ?string $userName = null, ?string $password = null) {
		try {
			parent::__construct($resource, $userName, $password);
		} catch (Exception $e) {
			AppLog::error("An error occurred when connecting to the data source: " . $e->getMessage(), __FILE__, __LINE__, __FUNCTION__);
			trigger_error("An internal error occurred when connecting to a data source (ERR_DATACONTROLLER_CONSTRUCTOR_EXCEPTION).", E_USER_ERROR);
		}
	}

	/**
	 * Escape any SQL wildcards found in some text.
	 *
	 * This helper function escapes a user-provided piece of text such that it can be safely used in a SQL _LIKE_ clause
	 * without any characters provided by the user that have special meanings interfering with the results.
	 *
	 * @param $text string The text to escape.
	 *
	 * @return string The escaped text.
	 */
	public static function escapeSqlWildcards(string $text): string {
		static $s_from = ["%", "_"];
		static $s_to = ["\\%", "\\_"];

		return str_replace($s_from, $s_to, $text);
	}

	/**
	 * Translate from _de-facto_ to SQL wildcards.
	 *
	 * This helper function translates _*_ and _?_ in a user-provided piece of text to *%* and *_* respectively so that
	 * it can be used in a SQL _LIKE_ clause with the intended meaning.
	 *
	 * @see sqlToDefactoWildcards()
	 *
	 * @param $text string The text to translate.
	 *
	 * @return string The translated text.
	 */
	public static function defactoToSqlWildcards(string $text): string {
		static $s_from = ["*", "?"];
		static $s_to = ["%", "_"];

		return str_replace($s_from, $s_to, $text);
	}

	/**
	 * Translate from \em de-facto to SQL wildcards.
	 *
	 * This helper function translates _*_ and _?_ in a user-provided piece of text to _.*_ and _._ respectively so that
	 * it can be used in a SQL _REGEX_ clause with the intended meaning.
	 *
	 * @param $text string The text to translate.
	 *
	 * @return string The translated text.
	 */
	public static function defactoToRegExpWildcards(string $text): string {
		static $s_from = ["*", "?"];
		static $s_to = [".*", "."];

		return str_replace($s_from, $s_to, $text);
	}

	/**
	 * Translate from SQL to *de-facto* wildcards.
	 *
	 * This helper function translates *%* and *_* in a user-provided piece of text to _*_ and _?_ respectively.
	 *
	 * @see defactoToSqlWildcards()
	 *
	 * @param $text string The text to translate.
	 *
	 * @return string The translated text.
	 */
	public static function sqlToDefactoWildcards(string $text): string {
		static $s_from = ["%", "_"];
		static $s_to = ["*", "?"];

		return str_replace($s_from, $s_to, $text);
	}
}
