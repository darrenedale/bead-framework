<?php

/**
 * Defines the _DataValidationReport_ class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 *
 * ### Changes
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
 * - (2013-12-10) First version of this file.
 *
 * @file DataValidationReport.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit;

/**
 * A class representing a report on the validation of some data.
 *
 * Reports provide information about the fields in the data object that contain errors and the fields that contain data
 * that gives rise to warnings.
 *
 * The report consists of a series of user-presentable error and warning messages, each of which may be attached to zero
 * or more fields in the data. Messages that are attached to no fields are considered general errors or warnings for the
 * data as a whole. In some cases, such as when data may contain information for one field or another field but not both,
 * messages need to be attached to more than one field.
 *
 * Creators of reports add errors and warnings using the `addError()` and `addWarning()` methods. If the messages
 * provided require translation, this should be done before the message is added to the report. Messages are added to
 * individual fields by passing an array of field names as the second argument. Any one of these field names may be an
 * empty string to indicate that the message should be attached to the DAO as a whole.
 *
 * Receivers of error reports can query the overall number of errors and warnings with `errorCount()` and
 * `warningCount()`. The list of fields with errors or warnings is available using `errorFields()` and `warningFields()`
 * respectively. To fetch the actual error or warning messages for a specific field, call `errors()` or `warnings()`
 * respectively, providing the field name in which you are interested. If you provide no field name all errors or
 * warnings will be provided. To get access to the errors or warnings associated with the data as a whole (rather than
 * individual fields), pass an empty string.
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
 * @class DataValidationReport
 * @author Darren Edale
 * @ingroup libequit
 * @package libequit
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class DataValidationReport implements JsonExportable {
	/** @var array The list of errors. */
	private $m_errors = [];

	/** @var array The list of warnings. */
	private $m_warnings = [];

	/**
	 * Initialise a new validation report.
	 *
	 * The validation report will be empty - no errors and no warnings.
	 */
	public function __construct() {
	}

	/**
	 * Fetch the number of errors in the validation report.
	 *
	 * When specifying the _$field_ parameter:
	 * - `null` means all errors regardless of which field they apply to;
	 * - an empty string means all errors that are not related to a specific field
	 * - any other string means errors for the field with that name
	 *
	 * @param $field string|null _optional_ The field whose errors should be counted.
	 *
	 * @return int The number of errors.
	 */
	public function errorCount(?string $field = null): int {
		$count = 0;

		if(!isset($field)) {
			return count($this->m_errors);
		}
		else {
			foreach($this->m_errors as $error) {
				if(in_array($field, $error->fields)) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Fetch the number of errors in the validation report.
	 *
	 * When specifying the _$field_ parameter:
	 * - `null` means all errors regardless of which field they apply to;
	 * - an empty string means all errors that are not related to a specific field;
	 * - any other string means errors for the field with that name.
	 *
	 * @param $field string|null _optional_ The field whose errors should be counted.
	 *
	 * @return int The number of errors.
	 */
	public function warningCount(?string $field = null): int {
		if(!isset($field)) {
			return count($this->m_warnings);
		}

		$count = 0;

		foreach($this->m_warnings as $warning) {
			if(in_array($field, $warning->fields)) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Fetch a list of the fields that have errors.
	 *
	 * The returned array will contain an entry with an empty string if there are errors that are not related to a
	 * specific field. The array will be empty if there are no errors.
	 *
	 * @return array[string] The fields with errors.
	 */
	public function errorFields(): array {
		$ret = [];

		foreach($this->m_errors as $error) {
			$ret = array_merge($ret, $error->fields);
		}

		return array_unique($ret);
	}

	/**
	 * Fetch a list of the fields that have warnings.
	 *
	 * The returned array will contain an entry with an empty string if there are warnings that are not related to a
	 * specific field. The array will be empty if there are no warnings.
	 *
	 * @return array[string] The fields with warnings.
	 */
	public function warningFields(): array {
		$ret = [];

		foreach($this->m_warnings as $warning) {
			$ret = array_merge($ret, $warning->fields);
		}

		return array_unique($ret);
	}

	/**
	 * Fetch a list of error messages.
	 *
	 * When specifying the \b $field parameter:
	 * - `null` means all errors regardless of which field they apply to;
	 * - an empty string means all errors that are not related to a specific field;
	 * - any other string means errors for the field with that name.
	 *
	 * The array will be empty if there are no errors matching the requested
	 * set.
	 *
	 * @param $field string|null _optional_ The field whose errors should be returned.
	 *
	 * @return array[string] The requested error messages.
	 */
	public function errors(?string $field = null): array {
		$ret = [];

		if(!isset($field)) {
			foreach($this->m_errors as $error) {
				$ret[] = $error->message;
			}
		}
		else {
			foreach($this->m_errors as $error) {
				if(in_array($field, $error->fields)) {
					$ret[] = $error->message;
				}
			}
		}

		return $ret;
	}

	/**
	 * Fetch a list of warning messages.
	 *
	 * @param $field string|null _optional_ The field whose warnings should be returned.
	 *
	 * When specifying the _$field_ parameter:
	 * - `null` means all warnings regardless of which field they apply to;
	 * - an empty string means all warnings that are not related to a specific field;
	 * - any other string means warnings for the field with that name.
	 *
	 * The array will be empty if there are no warnings matching the requested
	 * set.
	 *
	 * @return array[string] The requested warning messages.
	 */
	public function warnings(?string $field = null): array {
		$ret = [];

		if(!isset($field)) {
			foreach($this->m_warnings as $warning) {
				$ret[] = $warning->message;
			}
		}
		else {
			foreach($this->m_warnings as $warning) {
				if(in_array($field, $warning->fields)) {
					$ret[] = $warning->message;
				}
			}
		}

		return $ret;
	}

	/**
	 * Helper to add a message to an array of messages.
	 *
	 * This is a helper for addError() and addWarning() to remove boilerplate and ensure the work consistently.
	 *
	 * @param $target array The target array to which to add the message.
	 * @param $msg string The message message.
	 * @param $fields string|array|null _optional_ The field(s) to which the message relates.
	 */
	protected final function addMessage(array & $target, string $msg, $fields = null): void {
		assert(!isset($fields) || is_string($fields) || is_array($fields));

		if(is_array($fields)) {
			foreach($fields as $field) {
				assert(is_string($field));
			}
		}

		if(is_string($fields) && !empty($fields)) {
			$fields = [$fields];
		}
		else if(!is_array($fields)) {
			$fields = [];
		}

		$target[] = (object) ["fields" => $fields, "message" => $msg];
	}

	/**
	 * Add an error to the validation report.
	 *
	 * If the error is not related to any specific fields, the _$fields_ parameter may be omitted, or set to `null`.
	 * DAOs may create individual error messages that apply to as many fields as they wish. DAOs are free to label their
	 * fields however they wish; however, in order to be most useful, DAO's should document how they name their fields
	 * and should use those names consistently.
	 *
	 * The content of messages should be translated to the appropriate language before being added to the report - the
	 * report will not do any translation.
	 *
	 * @param $msg string The error message.
	 * @param $fields string|array|null _optional_ The field(s) to which the message relates.
	 */
	public function addError(string $msg, $fields = null): void {
		self::addMessage($this->m_errors, $msg, $fields);
	}

	/**
	 * Add warning to the validation report.
	 *
	 * @param $msg string The warning message.
	 * @param $fields string|array|null _optional_ The field(s) to which the message relates.
	 *
	 * If the warning is not related to any specific fields, the _$fields_ parameter may be omitted, or set to `null`.
	 * DAOs may create individual warning messages that apply to as many fields as they wish. DAOs are free to label
	 * their fields however they wish; however, in order to be most useful, DAO's should document how they name their
	 * fields and should use those names consistently.
	 *
	 * The content of messages should be translated to the appropriate language before being added to the report - the
	 * report will not do any translation.
	 */
	public function addWarning(string $msg, $fields = null): void {
		self::addMessage($this->m_errors, $msg, $fields);
	}

	/**
	 * Get a JSON representation of the report.
	 *
	 * The JSON is structured like this:
	 *     {
	 *         formatVersion: 1,
	 *         errors: [
	 *             {
	 *                 fields: [...],
	 *                 message: ""
	 *             },
	 *             {
	 *                 fields: [...],
	 *                 message: ""
	 *             },
	 *             ...
	 *         ],
	 *         warnings: [
	 *             {
	 *                 fields: [...],
	 *                 message: ""
	 *             },
	 *             {
	 *                 fields: [...],
	 *                 message: ""
	 *             },
	 *             ...
	 *         ],
	 *     }
	 *
	 * When the structure changes, the formatVersion will change. This can be used to work out how to parse the JSON.
	 *
	 * There are currently no supported options - the JSON generated cannot currently be customised.
	 *
	 * @param array|null $options An array of options.
	 *
	 * @return string The report as JSON.
	 */
	public function toJson(?array $options = null): string {
		$warningsJson = json_encode($this->m_warnings);
		$errorsJson = json_encode($this->m_errors);

		return <<<JSON
{
	"formatVersion: 1,
	"warnings": $warningsJson,
	"errors": $errorsJson
}
JSON;
	}
}
