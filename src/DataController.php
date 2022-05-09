<?php

/**
 * Defines the _DataController_ class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/User.php
 *
 * ### Todo
 * - proxies for reading related entities on-the-fly.
 *
 * ### Changes
 * - (2018-10) separated out application-specific functionality to create a generic, reusable base class.
 * - (2018-01) disabled (commented out) some methods that are not currently in use.
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from `array()`.
 * - (2015-11-13) Fixed a bunch of uses of class constants without self:: qualifier.
 * - (2013-12-10) First version of this file.
 *
 * @file DataController.php
 * @author Darren Edale
 * @version 1.2.0
 * @package libequit
 * @date Jan 2018
 */

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
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This module does not provide an API.
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
 * @package libequit
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class DataController extends PDO {
	protected const DateTimeFormat = "Y-m-d H:i:s";
	protected const DateFormat     = "Y-m-d";
	protected const TimeFormat     = "H:i:s";

	// field types for use when providing entity mappings
	public const IntField      = 0;
	public const CharField     = 1;
	public const BoolField     = 2;
	public const DateField     = 3;
	public const TimeField     = 4;
	public const DateTimeField = 5;
	public const SetField      = 6;
	public const EnumField     = 7;

	public const ErrOk                       = 0;
	public const ErrInvalidData              = 1;
	public const ErrNoStatement              = 2;
	public const ErrNoStatementParameterBind = 3;
	public const ErrExecuteStatementFailed   = 4;

	// these two are effectively the same set of mappings, except one is indexed by db entity and the other by DVO class
	// name
	/** @var array Maps database entities to PHP classes. */
	private array $m_entityMappings = [];

	/** @var array Maps PHP classes to database entities. */
	private array $m_classMappings = [];

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
		}
		catch(Exception $e) {
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

	/* methods to translate data into/out of the database */

	/**
	 * Convert data from a *SET* field in the database to a PHP array.
	 *
	 * @param $set string The *SET* data to convert.
	 *
	 * Duplicate values will be removed, as will empty values. If the input is an empty string, an empty array will
	 * result.
	 *
	 * @return array[string] The array extracted from the *SET* field data.
	 */
	public static function setToArray(string $set): array {
		$ret = array_unique(explode(",", $set));
		removeEmptyElements($ret);
		return $ret;
	}

	/**
	 * Convert a PHP array to data suitable for storage in a *SET* field in the database.
	 *
	 * This method converts an array of values into the text format expected by the database when storing data in *SET*
	 * fields. It must be possible to coerce every value in the array to a string. The method will not check this, and
	 * if it is not the case a PHP error will occur. Validate the content of the array before it is passed to this
	 * method.
	 *
	 * @param $array array[mixed] The array to convert.
	 *
	 * @return string The set data for the array.
	 */
	public static function arrayToSet(array $array): string {
		return implode(",", array_unique($array));
	}

	/**
	 * Convert a date value to a string.
	 *
	 * The date is converted to the format in which dates are stored in fields in the database.
	 *
	 * @see DateFormat
	 *
	 * @param $date DateTime the date value to convert.
	 *
	 * @return string The date in the appropriate format.
	 */
	public static function dateToString(DateTime $date): string {
		return $date->format(self::DateFormat);
	}

	/**
	 * Convert a date and time value to a string.
	 *
	 * The date and time is converted to the format in which dates and times are stored in fields in the database.
	 *
	 * @see DateTimeFormat
	 *
	 * @param $dateTime DateTime the time value to convert.
	 *
	 * @return string The date and time in the appropriate format.
	 */
	public static function dateTimeToString(DateTime $dateTime): string {
		return $dateTime->format(self::DateTimeFormat);
	}

	/**
	 * Convert a time value to a string.
	 *
	 * The time is converted to the format in which times are stored in fields in the database.
	 *
	 * @see TimeFormat
	 *
	 * @param $time DateTime the time value to convert.
	 *
	 * @return string The time in the appropriate format.
	 */
	public static function timeToString(DateTime $time): string {
		return $time->format(self::TimeFormat);
	}

	/**
	 * Convert a date-time string to a DateTime object.
	 *
	 * @param string $dateTimeStr The date-time string to parse.
	 *
	 * @return \DateTime|null The parsed _DateTime_, or _null_ on error.
	 */
	public static function stringToDateTime(string $dateTimeStr): ?DateTime {
		$ret = DateTime::createFromFormat(self::DateTimeFormat, $dateTimeStr);

		if(false === $ret) {
			return null;
		}

		return $ret;
	}

	/**
	 * Convert a date string to a DateTime object.
	 *
	 * @param string $dateStr The date string to parse.
	 *
	 * @return \DateTime|null The parsed _DateTime_, or _null_ on error.
	 */
	public static function stringToDate(string $dateStr): ?DateTime {
		$ret = DateTime::createFromFormat(self::DateFormat, $dateStr);

		if(false === $ret) {
			return null;
		}

		return $ret;
	}

	/**
	 * Convert a time string to a DateTime object.
	 *
	 * @param string $timeStr The time string to parse.
	 *
	 * @return \DateTime|null The parsed _DateTime_, or _null_ on error.
	 */
	public static function stringToTime(string $timeStr): ?DateTime {
		$ret = DateTime::createFromFormat(self::TimeFormat, $timeStr);

		if(false === $ret) {
			return null;
		}

		return $ret;
	}

	/**
	 * Convert a boolean to an integer.
	 *
	 * Most database engines store boolean values as an integer that is 0 if _false_ or non-0 if _true_. This method can
	 * be used to convert a PHP _bool_ to the required value. While this is clearly trivial to implement inline wherever
	 * required, this method can be useful as a read or write filter.
	 *
	 * @param $bool bool The boolean value to convert.
	 *
	 * @return int 1 for true, 0 for false.
	 */
	public static function boolToInt(bool $bool): int {
		return ($bool ? 1 : 0);
	}

	/**
	 * Check whether a primary key mapping provided to _addEntityMapping()_ is valid.
	 *
	 * If the mapping is not valid, at least one message will be output to the error log.
	 *
	 * @param \StdClass $mapping The mapping to check.
	 *
	 * @return bool _true_ if the mapping is valid, _false_ if not.
	 */
	private static function isValidPrimaryKeyMapping(StdClass $mapping) : bool {
		if(!isset($mapping->fieldName) || !is_string($mapping->fieldName) || empty($mapping->fieldName)) {
			AppLog::error("invalid field name for primary key mapping", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!isset($mapping->propertyName) || !is_string($mapping->propertyName) || !preg_match("/^[a-zA-Z_][a-zA-Z0-9_]*$/", $mapping->propertyName)) {
			AppLog::error("invalid property name for primary key mapping", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		return true;
	}

	/**
	 * Check whether a field definition provided to _addEntityMapping()_ is valid.
	 *
	 * If the definition is not valid, at least one message will be output to the error log.
	 *
	 * @param $field StdClass The field definition.
	 *
	 * @return bool _true_ if the field definition is valid, _false_ otherwise.
	 */
	private static function isValidFieldDefinition(StdClass $field): bool {
		if(!isset($field->type) || !is_int($field->type)) {
			AppLog::error("invalid field type", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		switch($field->type) {
			case self::IntField:
			case self::CharField:
			case self::BoolField:
			case self::EnumField:
			case self::SetField:
			case self::DateField:
			case self::TimeField:
			case self::DateTimeField:
				break;

			default:
				AppLog::error("invalid field type", __FILE__, __LINE__, __FUNCTION__);
				return false;
		}

		if(isset($field->accessor) && (!is_string($field->accessor) || !preg_match("/^[a-zA-Z_][a-zA-Z0-9_]*$/", $field->accessor))) {
			AppLog::error("invalid accessor method name", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(isset($field->mutator) && (!is_string($field->mutator) || !preg_match("/^[a-zA-Z_][a-zA-Z0-9_]*$/", $field->mutator))) {
			AppLog::error("invalid mutator method name", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		return true;
	}

	/**
	 * Add an entity mapping to the _DataController_ instance.
	 *
	 * An entity mapping maps a database entity to a class such that an instance of the class can be read from and/or
	 * written to the database. The mapping effectively associates a database table to a class type, defines the columns
	 * in the table and specifies how they map to properties of the class instance. The class need not exist when the
	 * mapping is created, but it must be defined when the mapping is used.
	 *
	 * The _$idFieldMapping_ is an object with two properties - _fieldName_ and _propertyName_. These are the name of
	 * the field in the database and the name of the property in the object respectively, and these define the primary
	 * key and unique object identifier for the object-entity mapping. For example, suppose you have a _names_ table
	 * as follows:
	 *
	 *     CREATE TABLE names (
	 *         id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	 *	       firstname VARCHAR(200) NOT NULL DEFAULT '',
	 *	       lastname VARCHAR(200) NOT NULL DEFAULT ''
	 *     )
	 *
	 * and a Name class as follows:
	 *
	 *     class Name {
	 *         private $m_id = null;
	 *         private $m_first = "";
	 *         private $m_last = "";
	 *
	 *         public function id(): ?int {
	 *             return $this->m_id;
	 *         }
	 *
	 *         public function setFirstname(string $first): void {
	 *             $this->m_first = $first;
	 *         }
	 *
	 *         public function firstname(): string {
	 *             return $this->m_first;
	 *         }
	 *
	 *         public function setLastname(string $last): void {
	 *             $this->m_last = $last;
	 *         }
	 *
	 *         public function lastname(): string {
	 *             return $this->m_last;
	 *         }
	 *     }
	 *
	 * Then your _$primaryKeyMapping_ would be
	 *
	 *     (object) ["fieldName" => "id", "propertyName" => "m_id"];
	 *
	 *
	 * The _$fields_ array is keyed by the name of the field in the database table. Each entry has the following
	 * properties:
	 * - **type** int The type of the field. This is required, and must be one of the class field type constants. This
	 *   will be used to determine how to transform the data from the database before it is provided to the DVO mutator
	 *   method for the field.
	 * - **accessor** _string_ The name of the method to use to read the field's data from the object. Just provide the
	 *   name of the method, do not include the class name nor any arguments or parentheses. The accessor method will be
	 *   validated when the mapping is created, and verified whenever the mapping is used. It must be callable with no
	 *   arguments.
	 *   The accessor is optional. If not given, the accessor will default to the name of the field (i.e.
	 *   _$class::$fieldName()_ will be used as the accessor).
	 * - **mutator** _string_ The name of the method to use to write the field's data to the object. Just provide the
	 *   name of the method, do not include the class name nor the arguments or parentheses. The mutator method will be
	 *   validated when the mapping is created, and verified whenever the mapping is used. The mutator must accept at
	 *   least one argument of a type that can implicitly be coerced from the PHP type appropriate to the field type
	 *   specified in the **type** property.
	 *   The mutator is optional. If not given, the mutator will default to the upper-cased name of the field prefixed
	 *   with _set_ (i.e. _$class::set$fieldName()_ will be used as the mutator).
	 *
	 * Mutators will be given data of a type determined by the field type given in the mapping. The types are as
	 * follows:
	 * | **Field type**                | **Mutator argument type** |
	 * |-------------------------------|---------------------------|
	 * | DataController::IntField      | int                       |
	 * | DataController::CharField     | string                    |
	 * | DataController::BoolField     | bool                      |
	 * | DataController::EnumField     | string                    |
	 * | DataController::SetField      | array[string]             |
	 * | DataController::DateField     | DateTime                  |
	 * | DataController::TimeField     | DateTime                  |
	 * | DataController::DateTimeField | DateTime                  |
	 *
	 * Generally, if you design your DVO classes with well-named accessors and mutators, you won't need to specify much
	 * beyond the field type in the field mapping data. As a crude example, the class
	 *
     *     class Person {
	 *         private $m_id = null;
     *         private $m_firstname = "";
     *         private $m_lastname = "";
     *
     *         public function id(): ?int {
     *             return $this->m_id;
     *         }
     *
     *         public function firstname(): string {
     *             return $this->m_firstName;
     *         }
     *
     *         public function setFirstname(string $first): void {
     *             $this->m_firstname = $first;
     *         }
     *
     *         public function lastname(): string {
     *             return $this->m_lastName;
     *         }
     *
     *         public function setLastname(string $last): void {
     *             $this->m_lastname = $last;
     *         }
     *     }
	 *
	 * can be used as a DVO by adding the following mapping:
	 *
	 *     $db->addEntityMapping("person", "Person", (object) ["fieldName" => "id", "propertyName" => "m_id"], [
	 *     		"firstname" => (object) ["type" => DataController::CharField],
	 *     		"lastname" => (object) ["type" => DataController::CharField],
	 * 		]);
	 *
	 * If adding the mapping fails, the error log will contain at least one useful message.
	 *
	 * @param string $table The entity to map.
	 * @param string $class The object class to which to map it.
	 * @param StdClass $primaryKeyMapping The field mapping for the primary key.
	 * @param array[string => StdClass] $fields The field mappings.
	 *
	 * @return bool _true_ if the mapping was successfully added, _false_ if adding the mapping failed for some reason.
	 */
	public function addEntityMapping(string $table, string $class, StdClass $primaryKeyMapping, array $fields): bool {
		if(empty($table)) {
			AppLog::error("invalid table name in entity mapping: \"$table\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(empty($class)) {
			AppLog::error("invalid class name in entity mapping: \"$class\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(isset($this->m_entityMappings[$table])) {
			AppLog::error("entity \"$table\" is already mapped", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(isset($this->m_classMappings[$class])) {
			AppLog::error("class \"$class\" is already mapped", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!self::isValidPrimaryKeyMapping($primaryKeyMapping)) {
			AppLog::error("invalid primary key mapping", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(0 == count($fields)) {
			AppLog::error("mapping of entity \"$table\" to class \"$class\" must contain at least one field", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		foreach($fields as $fieldName => $fieldDefinition) {
			if(!self::isValidFieldDefinition($fieldDefinition)) {
				AppLog::error("invalid mapping definition for field \"$fieldName\"", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}
		}

		$mapping            = new StdClass();
		$mapping->tableName = $table;
		$mapping->className = $class;
		$mapping->primaryKeyFieldName = $primaryKeyMapping->fieldName;
		$mapping->primaryKeyPropertyName = $primaryKeyMapping->propertyName;
		$mapping->fields    = [];

		foreach($fields as $fieldName => $fieldDefinition) {
			if($fieldName == $mapping->primaryKeyFieldName) {
				AppLog::error("field \"$fieldName\" shadows the primary key", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(in_array($fieldName, $mapping->fields)) {
				AppLog::error("field \"$fieldName\" mapped twice", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$fieldMapping            = new StdClass();
			$fieldMapping->type = $fieldDefinition->type;
			$fieldMapping->fieldName = $fieldName;
			$fieldMapping->accessor  = ($fieldDefinition->accessor ?? $fieldName);
			$fieldMapping->mutator   = ($fieldDefinition->mutator ?? "set" . ucfirst($fieldName));

			switch($fieldMapping->type) {
				case self::IntField:
					$fieldMapping->writeFilter = function(int $val) {
						return "$val";
					};
					$fieldMapping->readFilter  = function($data) {
						$ret = filter_var($data, FILTER_VALIDATE_INT);

						if(false === $ret) {
							throw new Exception("expected int value, found \"$data\" when reading from database");
						}

						return $ret;
					};
					break;

				case self::BoolField:
					$fieldMapping->writeFilter = function(bool $val) {
						return ($val ? 1 : 0);
					};
					$fieldMapping->readFilter  = function($data) {
						return 0 != $data;
					};
					break;

				case self::DateField:
					$fieldMapping->writeFilter = function(DateTime $val) {
						return $val->format(self::DateFormat);
					};
					$fieldMapping->readFilter  = function(string $data) {
						return self::stringToDate($data);
					};
					break;

				case self::TimeField:
					$fieldMapping->writeFilter = function(DateTime $val) {
						return $val->format(self::TimeFormat);
					};
					$fieldMapping->readFilter  = function(string $data) {
						return self::stringToTime($data);
					};
					break;

				case self::DateTimeField:
					$fieldMapping->writeFilter = function(DateTime $val) {
						return $val->format(self::DateTimeFormat);
					};
					$fieldMapping->readFilter  = function(string $data) {
						return self::stringToDateTime($data);
					};
					break;

				case self::SetField:
					$fieldMapping->writeFilter = function(array $val) {
						return self::arrayToSet($val);
					};
					$fieldMapping->readFilter  = function(string $data) {
						return self::setToArray($data);
					};
					break;
			}

			$mapping->fields[$fieldName] = $fieldMapping;
		}

		$this->m_entityMappings[$table] = $mapping;
		$this->m_classMappings[$class]  = $mapping;
		return true;
	}

	/**
	 * Helper function to read a row of data into a DVO.
	 *
	 * This is generally used by DVO fetch methods to create an instance of the DVO once the data has been read from the
	 * database. Since there are a number of ways to fetch the data, serving different purposes, this code has been
	 * abstracted out to avoid repetition and ease maintenance.
	 *
	 * @internal
	 *
	 * @param string $class The class name of the DVO to read into.
	 * @param StdClass $mapping The mapping object that describes how the data gets into the object.
	 * @param array $data The data to read into the object.
	 *
	 * @return mixed|null An instance of the requested class, or _null_ if the data could not be read into an instance.
	 */
	private static function readObject(string $class, StdClass $mapping, array $data) {
		try {
			$classInfo = new ReflectionClass($class);
		}
		catch(ReflectionException $err) {
			AppLog::error("class $class is not available: " . $err->getMessage(), __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if($classInfo->getConstructor() && 0 != $classInfo->getConstructor()->getNumberOfRequiredParameters()) {
			AppLog::error("no default constructor for class $class", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		try {
			$primaryKeyPropertyInfo = $classInfo->getProperty($mapping->primaryKeyPropertyName);
		}
		catch(ReflectionException $err) {
			AppLog::error("primary key property \"{$mapping->primaryKeyPropertyName}\" is not available: {$err->getMessage()}", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(!$primaryKeyPropertyInfo) {
			AppLog::error("primary key property \"{$mapping->primaryKeyPropertyName}\" is not available in class \"$class\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if($primaryKeyPropertyInfo->isStatic()) {
			AppLog::error("primary key property \"{$mapping->primaryKeyPropertyName}\" is static in class \"$class\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(!$primaryKeyPropertyInfo->isPublic()) {
			$primaryKeyPropertyInfo->setAccessible(true);
		}

		$dao = new $class();

		// TODO use filter_var to enforce int?
		$primaryKeyPropertyInfo->setValue($dao, $data[$mapping->primaryKeyFieldName]);

		foreach($mapping->fields as $fieldName => $fieldMapping) {
			$fieldData = (isset($fieldMapping->readFilter) ? call_user_func($fieldMapping->readFilter, $data[$fieldName]) : $data[$fieldName]);

			try {
				$mutator = new ReflectionMethod($dao, $fieldMapping->mutator);
			}
			catch(ReflectionException $err) {
				AppLog::error("exception when referencing mutator method $class::{$fieldMapping->mutator} for field mapping \"$fieldName\": " . $err->getMessage(), __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			if(0 == $mutator->getNumberOfParameters()) {
				AppLog::error("mutator method $class::{$fieldMapping->mutator} for field mapping \"$fieldName\" does not accept any arguments", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			if(1 < $mutator->getNumberOfRequiredParameters()) {
				AppLog::error("mutator method $class::{$fieldMapping->mutator} for field mapping \"$fieldName\" expects more than one non-optional argument", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			$param = $mutator->getParameters()[0];

			if($param->hasType()) {
				$expected = $param->getType();

				if ($expected) {
					$expected = $expected->getName();
				}

				if(is_object($fieldData)) {
					$actual = get_class($fieldData);
				} else {
					switch(true) {
						case is_int($fieldData):
							$actual = "int";    // gettype() returns "integer"
							break;

						case is_bool($fieldData):
							$actual = "bool";    // gettype() returns "boolean"
							break;

						case is_float($fieldData):
							$actual = "float";    // gettype() returns "double"
							break;

						default:
							$actual = gettype($fieldData);
					}
				}

				// param and arg types must be same (or compatible: int is allowed to be implicitly cast to float)
				if($expected != $actual && !("float" == $expected && "int" == $actual) && !($param->allowsNull() && is_null($fieldData))) {
					AppLog::error("mutator method $class::{$fieldMapping->mutator}() expects first argument to be of type $expected but the data to be provided is of type $actual", __FILE__, __LINE__, __FUNCTION__);
					return null;
				}
			}

			if($mutator->hasReturnType()) {
				$returnType = $mutator->getReturnType();

				switch($returnType ? $returnType->getName() : "") {
					case "bool":
						$result = $mutator->invoke($dao, $fieldData);
						break;

					case "int":
						$result = (0 == $mutator->invoke($dao, $fieldData));
						break;

					default:
						$mutator->invoke($dao, $fieldData);
						$result = true;
				}
			} else {
				$mutator->invoke($dao, $fieldData);
				$result = true;
			}

			if(!$result) {
				AppLog::error("call to $class::{$fieldMapping->mutator}() failed (result: " . stringify($result) . ")", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}
		}

		return $dao;
	}

	/**
	 * Fetch instances of a mapped entity from the database, matching on multiple fields.
	 *
	 * @param string $class The DVO class of the mapped entity to be searched.
	 * @param array $criteria The search criteria.
	 *
	 * @return array|null Objects representing the matched entities. This will be empty if no matches are found, or
	 * _null_ on error.
	 */
	public function findByFields(string $class, array $criteria): ?array {
		if(!isset($this->m_classMappings[$class])) {
			AppLog::error("no entity mapping for class \"$class\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$classMapping =& $this->m_classMappings[$class];

		foreach($criteria as $matchFieldName => $value) {
			if(!isset($classMapping->fields[$matchFieldName])) {
				AppLog::error("field \"$matchFieldName\" not found in entity mapping for class \"$class\"", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}
		}

		$sql        = "SELECT `t`.`{$classMapping->primaryKeyFieldName}`";

		foreach($classMapping->fields as $fieldName => $fieldMapping) {
			$sql .= ", `t`.`$fieldName`";
		}

		$sql        .= " FROM `{$classMapping->tableName}` AS `t` WHERE ";
		$firstField = true;
		$i          = 1;

		foreach($criteria as $matchFieldName => $matchFieldValue) {
			if($firstField) {
				$firstField = false;
			} else {
				$sql .= " AND ";
			}

			$sql .= "`t`.`$matchFieldName` = :value$i";
			++$i;
		}

		if("User" == $class) {
			AppLog::message("find users SQL: " . $sql);
			AppLog::message("with: " . print_r($criteria, true));
		}

		$stmt = $this->prepare($sql);

		if(!$stmt instanceof PDOStatement) {
			[$code, , $msg] = $this->errorInfo();
			AppLog::error("failed to prepare entity find statement for \"$class\" (\"{$classMapping->tableName}\"): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$i = 1;

		foreach($criteria as $matchFieldName => $matchFieldValue) {
			// this class is in complete control of the writeFilter property, so we don't need to reflect to verify it -
			// we know it is viable
			if(isset($classMapping->fields[$matchFieldName]->writeFilter)) {
				$matchFieldValue = call_user_func($classMapping->fields[$matchFieldName]->writeFilter, $matchFieldValue);
			}

			if(!$stmt->bindValue(":value$i", $matchFieldValue)) {
				[$code, , $msg] = $stmt->errorInfo();
				AppLog::error("failed to bind value " . stringify($matchFieldValue) . " to entity find statement for \"$class\" (`{$classMapping->tableName}`.`$matchFieldName): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			++$i;
		}

		$ret = [];
		$stmt->setFetchMode(PDO::FETCH_ASSOC);

		if(!$stmt->execute()) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to execute find statement for \"$class\" (`{$classMapping->tableName}`): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		foreach($stmt as $data) {
			$dao = self::readObject($class, $classMapping, $data);

			if(!isset($dao)) {
				AppLog::error("failed to read one or more rows into $class objects", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			$ret[] = $dao;
		}

		return $ret;
	}

	/**
	 * Fetch instances of a mapped entity from the database, matching on a single field.
	 *
	 * The value to search for should be the value used for the field when mapped to an object. For example, a DateTime
	 * object (not a string) to search in a datetime field in the database.
	 *
	 * @param string $class The DVO class of the mapped entity to be searched.
	 * @param string $matchFieldName The name of the entity field to match.
	 * @param mixed $value The value to search for in the field.
	 *
	 * @return array|null Objects representing the matched entities. This will be empty if no matches are found, or
	 * _null_ on error.
	 */
	public function findByField(string $class, string $matchFieldName, $value): ?array {
		static $s_statementCache = [];

		if(!isset($this->m_classMappings[$class])) {
			AppLog::error("no entity mapping for class \"$class\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$classMapping =& $this->m_classMappings[$class];

		if(!isset($classMapping->fields[$matchFieldName])) {
			AppLog::error("field \"$matchFieldName\" not found in entity mapping for class \"$class\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(!isset($s_statementCache[$class])) {
			$s_statementCache[$class] = [];
		}

		if(isset($s_statementCache[$class][$matchFieldName])) {
			$stmt = $s_statementCache[$class][$matchFieldName];
		} else {
			$sql        = "SELECT ";
			$firstField = true;

			foreach($classMapping->fields as $fieldName => $fieldMapping) {
				if($firstField) {
					$firstField = false;
				} else {
					$sql .= ", ";
				}

				$sql .= "`t`.`$fieldName`";
			}

			$sql  .= " FROM `{$classMapping->tableName}` `t` WHERE `t`.`$matchFieldName` = :value";
			$stmt = $s_statementCache[$class][$matchFieldName] = $this->prepare($sql);

			if(!$stmt instanceof PDOStatement) {
				[$code, , $msg] = $this->errorInfo();
				AppLog::error("failed to prepare entity find statement for \"$class\" (\"{$classMapping->tableName}\"): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}
		}

		// this class is in complete control of the writeFilter property, so we don't need to reflect to verify it -
		// we know it is viable
		if(isset($classMapping->fields[$matchFieldName]->writeFilter)) {
			$value = call_user_func($classMapping->fields[$matchFieldName]->writeFilter, $value);
		}

		if(!$stmt->bindValue(":value", $value)) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to bind value " . stringify($value) . " to entity find statement for \"$class\" (`{$classMapping->tableName}`.`$matchFieldName``): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$ret = [];
		$stmt->setFetchMode(PDO::FETCH_ASSOC);

		if(!$stmt->execute()) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to execute find statement for \"$class\" (`{$classMapping->tableName}`.`$matchFieldName``): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		foreach($stmt as $data) {
			$dao = self::readObject($class, $classMapping, $data);

			if(!isset($dao)) {
				AppLog::error("failed to read one or more rows into $class objects", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}

			$ret[] = $dao;
		}

		return $ret;
	}

	/**
	 * Fetch an object from the database.
	 *
	 * The class of object to fetch must have an entity mapping available. This can be achieved by a prior call to
	 * _addEntityMapping()_.
	 *
	 * @param string $class The class of the object to fetch.
	 * @param $id mixed The unique ID (primary key) of the object to fetch.
	 *
	 * @return mixed|null The object, or _null_ if the object can't be found or an error occurred.
	 */
	public function find(string $class, $id) {
		static $s_statementCache = [];

		if(!isset($this->m_classMappings[$class])) {
			AppLog::error("no entity mapping for class \"$class\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$classMapping =& $this->m_classMappings[$class];

		if(isset($s_statementCache[$class])) {
			$stmt = $s_statementCache[$class];
		} else {
			$sql        = "SELECT `t`.`{$classMapping->primaryKeyFieldName}`";

			foreach($classMapping->fields as $fieldName => $fieldMapping) {
				$sql .= ", `t`.`$fieldName`";
			}

			$sql  .= " FROM `{$classMapping->tableName}` `t` WHERE `t`.`{$classMapping->primaryKeyFieldName}` = :id";
			$stmt = $s_statementCache[$class] = $this->prepare($sql);

			if(!$stmt instanceof PDOStatement) {
				[$code, , $msg] = $this->errorInfo();
				AppLog::error("failed to prepare entity find statement for \"$class\" (\"{$classMapping->tableName}\"): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return null;
			}
		}

		if(!$stmt->bindValue(":id", $id)) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to bind value " . stringify($id) . " to entity find statement for \"$class\" (`{$classMapping->tableName}`.`{$classMapping->primaryKeyFieldName}`): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(!$stmt->execute()) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to execute find statement for \"$class\" (`{$classMapping->tableName}`.`{$classMapping->primaryKeyFieldName}` == $id): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$data = $stmt->fetch(PDO::FETCH_ASSOC);

		if(false === $data) {
			[$code, , $msg] = $stmt->errorInfo();

			if(0 != $code) {
				AppLog::error("error executing find statement for \"$class\" (`{$classMapping->tableName}`.`{$classMapping->primaryKeyFieldName}` == $id): [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			}

			return null;
		}

		$dao = self::readObject($class, $classMapping, $data);

		if(false !== $stmt->fetch()) {
			AppLog::error("failed to identify just one {$classMapping->tableName} entity in db with `{$classMapping->primaryKeyFieldName}` = $id", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $dao;
	}

	/**
	 * Insert content represented by a DVO into the database.
	 *
	 * The class of object provided must have an entity mapping available. This is usually achieved by a prior call to
	 * _addEntityMapping()_.
	 *
	 * @param $dao mixed The DVO to insert.
	 *
	 * @return bool _true_ if the DVO was inserted successfully, _false_ otherwise.
	 */
	public function insert($dao): bool {
		static $s_statementCache = [];
		$className = get_class($dao);

		if(!isset($this->m_classMappings[$className])) {
			AppLog::error("no entity mapping found for class \"$className\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$classMapping =& $this->m_classMappings[$className];

		try {
			$primaryKeyPropertyInfo = new ReflectionProperty($dao, $classMapping->primaryKeyPropertyName);
		}
		catch(ReflectionException $err) {
			AppLog::error("failed to interrogate primary key property \"{$classMapping->primaryKeyPropertyName}\" in DVO class $className");
			return false;
		}

		if($primaryKeyPropertyInfo->isStatic()) {
			AppLog::error("primary key property \"{$classMapping->primaryKeyPropertyName}\" is static in class \"$className\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(isset($s_statementCache[$className])) {
			$stmt = $s_statementCache[$className];
		} else {
			/** @noinspection SqlResolve */
			$sql = "INSERT INTO `{$classMapping->tableName}` SET ";
			$i   = 1;

			foreach($classMapping->fields as $fieldMapping) {
				if(1 < $i) {
					$sql .= ", ";
				}

				$sql .= "`{$fieldMapping->fieldName}` = :value$i";
				++$i;
			}

			$stmt = $s_statementCache[$className] = $this->prepare($sql);

			if(!($stmt instanceof PDOStatement)) {
				[$code, , $msg] = $this->errorInfo();
				AppLog::error("failed to prepare statement to insert \"$className\" object into \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}
		}

		$i = 1;

		foreach($classMapping->fields as $fieldMapping) {
			$value = $dao->{$fieldMapping->accessor}();

			if(isset($fieldMapping->writeFilter)) {
				$value = call_user_func($fieldMapping->writeFilter, $value);
			}

			if(!$stmt->bindValue(":value$i", $value)) {
				[$code, , $msg] = $stmt->errorInfo();
				AppLog::error("failed to bind value " . stringify($value) . " for field `{$fieldMapping->fieldName}` to statement to insert \"$className\" object into \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			++$i;
		}

		if(!$stmt->execute()) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to execute statement to insert \"$className\" object into \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!$primaryKeyPropertyInfo->isPublic()) {
			$primaryKeyPropertyInfo->setAccessible(true);
		}

		$primaryKeyPropertyInfo->setValue($dao, $this->lastInsertId());
		return true;
	}

	/**
	 * Update database content represented by a DVO.
	 *
	 * The class of object provided must have an entity mapping available. This is usually achieved by a prior call to
	 * _addEntityMapping()_.
	 *
	 * @param $dvo mixed The DVO to update.
	 *
	 * @return bool _true_ if the DVO was updated successfully, _false_ otherwise.
	 */
	public function update($dvo): bool {
		static $s_statementCache = [];
		$className = get_class($dvo);

		if(!isset($this->m_classMappings[$className])) {
			AppLog::error("no entity mapping found for class \"$className\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$classMapping =& $this->m_classMappings[$className];

		try {
			$primaryKeyPropertyInfo = new ReflectionProperty($dvo, $classMapping->primaryKeyPropertyName);
		}
		catch(ReflectionException $err) {
			AppLog::error("failed to interrogate primary key property \"{$classMapping->primaryKeyPropertyName}\" in DVO class $className");
			return false;
		}

		if($primaryKeyPropertyInfo->isStatic()) {
			AppLog::error("primary key property \"{$classMapping->primaryKeyPropertyName}\" is static in class \"$className\"", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!$primaryKeyPropertyInfo->isPublic()) {
			$primaryKeyPropertyInfo->setAccessible(true);
		}

		if(isset($s_statementCache[$className])) {
			$stmt = $s_statementCache[$className];
		} else {
			/** @noinspection SqlResolve */
			$sql = "UPDATE `{$classMapping->tableName}` SET ";
			$i   = 1;

			foreach($classMapping->fields as $fieldMapping) {
				if(1 < $i) {
					$sql .= ", ";
				}

				$sql .= "`{$fieldMapping->fieldName}` = :value$i";
				++$i;
			}

			$sql  .= " WHERE `id` = :id";
			$stmt = $s_statementCache[$className] = $this->prepare($sql);

			if(!($stmt instanceof PDOStatement)) {
				[$code, , $msg] = $this->errorInfo();
				AppLog::error("failed to prepare statement to update \"$className\" object in \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}
		}

		if(!$stmt->bindValue(":id", $primaryKeyPropertyInfo->getValue($dvo))) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to bind id " . stringify($primaryKeyPropertyInfo->getValue($dvo)) . " for primary key to statement to update \"$className\" object in \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$i = 1;

		foreach($classMapping->fields as $fieldMapping) {
			$value = $dvo->{$fieldMapping->accessor}();

			if(isset($fieldMapping->writeFilter)) {
				$value = call_user_func($fieldMapping->writeFilter, $value);
			}

			if(!$stmt->bindValue(":value$i", $value)) {
				[$code, , $msg] = $stmt->errorInfo();
				AppLog::error("failed to bind value " . stringify($value) . " for field `{$fieldMapping->fieldName}` to statement to update \"$className\" object in \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			++$i;
		}

		if(!$stmt->execute()) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to execute statement to update \"$className\" object in \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		return true;
	}

	/**
	 * Delete database content represented by a DVO.
	 *
	 * The class of object provided must have an entity mapping available. This is usually achieved by a prior call to
	 * _addEntityMapping()_.
	 *
	 * @param $daoOrClass mixed The object to delete or the name of the class of the object to delete.
	 * @param int|null $id If the first argument is the name of a class, this provides the ID of the object to delete.
	 * Otherwise it is ignored and should not be provided.
	 *
	 * @return bool _true_if the DVO was deleted successfully, _false_ otherwise.
	 */
	public function delete($daoOrClass, ?int $id = null): bool {
		static $s_statementCache = [];

		if(is_string($daoOrClass)) {
			if(!is_int($id) || 1 > $id) {
				AppLog::error("invalid primary key ID \"$id\" for $daoOrClass to delete", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$className = $daoOrClass;

			if(!isset($this->m_classMappings[$className])) {
				AppLog::error("no entity mapping found for class \"$className\"", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$classMapping =& $this->m_classMappings[$className];
		}
		else {
			$className = get_class($daoOrClass);

			if(!isset($this->m_classMappings[$className])) {
				AppLog::error("no entity mapping found for class \"$className\"", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$classMapping =& $this->m_classMappings[$className];

			try {
				$primaryKeyPropertyInfo = new ReflectionProperty($daoOrClass, $classMapping->primaryKeyPropertyName);
			}
			catch(ReflectionException $err) {
				AppLog::error("failed to interrogate primary key property \"{$classMapping->primaryKeyPropertyName}\" in DVO class $className");
				return false;
			}

			if($primaryKeyPropertyInfo->isStatic()) {
				AppLog::error("primary key property \"{$classMapping->primaryKeyPropertyName}\" is static in class \"$className\"", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			if(!$primaryKeyPropertyInfo->isPublic()) {
				$primaryKeyPropertyInfo->setAccessible(true);
			}

			$id = $primaryKeyPropertyInfo->getValue($daoOrClass);
		}

		if(isset($s_statementCache[$className])) {
			$stmt = $s_statementCache[$className];
		} else {
			/** @noinspection SqlResolve */
			$stmt = $s_statementCache[$className] = $this->prepare("DELETE FROM `{$classMapping->tableName}` WHERE `id` = :id");

			if(!($stmt instanceof PDOStatement)) {
				[$code, , $msg] = $this->errorInfo();
				AppLog::error("failed to prepare statement to delete \"$className\" object from \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}
		}

		if(!$stmt->bindValue(":id", $id)) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to bind id " . stringify($id) . " for primary key to statement to delete \"$className\" object from \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!$stmt->execute()) {
			[$code, , $msg] = $stmt->errorInfo();
			AppLog::error("failed to execute statement to delete \"$className\" object from \"{$classMapping->tableName}\": [$code] $msg", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		return true;
	}

}
