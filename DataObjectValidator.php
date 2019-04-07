<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 24/03/19
 * Time: 15:55
 */

namespace Equit;

/**
 * Interface (with helpers) for data validation classes.
 *
 * To create a validator for your own objects, subclass and implement the `validate()` method. Use the helpers to build
 * a validation report. Use object state to configure the validator for various scenarios for the object type being
 * validated (e.g. if certain fields are optional under certain circumstances).
 *
 * @package Equit
 */
abstract class DataObjectValidator {

	/**
	 * Private helper to add a message of some description to a validation report.
	 *
	 * @param \StdClass $report The report to augment.
	 * @param string $type The message type ("errors" or "warnings").
	 * @param string $field The name of the field that gave rise to the error/warning.
	 * @param string $msg The error/warning message.
	 */
	private static function addReportMessage(\StdClass $report, string $type, string $field, string $msg) {
		if(isset($report->$type->$field)) {
			$report->$type->$field[] = $msg;
		}
		else {
			$report->$type->$field = [$msg];
		}
	}

	/**
	 * Helper to add an error message to a validation report.
	 *
	 * @param \StdClass $report The report to augment.
	 * @param string $field The name of the field that gave rise to the error.
	 * @param string $msg The error message.
	 */
	protected static function addError(\StdClass $report, string $field, string $msg) {
		self::addReportMessage($report, "errors", $field, $msg);
	}

	/**
	 * Helper to add a warning message to a validation report.
	 *
	 * @param \StdClass $report The report to augment.
	 * @param string $field The name of the field that gave rise to the warning.
	 * @param string $msg The warning message.
	 */
	protected static function addWarning(\StdClass $report, string $field, string $msg) {
		self::addReportMessage($report, "warnings", $field, $msg);
	}

	/**
	 * Helper to create and initialise an empty validation report.
	 *
	 * @return \StdClass An empty validation report.
	 */
	protected static function createEmptyReport(): \StdClass {
		return (object) [
			"errors" => (object) [],
			"warnings" => (object) [],
		];
	}

	/**
	 * Validate an object.
	 *
	 * The validation report *must* have the following properties set:
	 * - **errors** \StdClass The error messages.
	 * - **warnings** \StdClass The warning messages.
	 *
	 * The errors and warnings properties of the report are both objects with the field names as properties. Any field
	 * with one or more errors will have a property in the `errors` object, the value of which is an array of strings
	 * containing the errors. The same goes for the `warnings` property. If no errors are found, `errors` will be an
	 * empty object; if no warnings are found, `warnings` will be an empty object.
	 *
	 * If the object is considered valid the report's `errors` property must be an empty object (i.e. no properties).
	 *
	 * An example report indicating two fields each have two errors. No other fields have any errors, an no fields have
	 * any warnings. Expressed as JSON:
	 *     {
	 *         errors: {
	 *             field1: ["Field 1 is too long.", "Field 1 must start with a digit.",],
	 *             field2: ["Field 2 is too short.", "Field 2 must start with a character from the Latin alphabet.",],
	 *         },
	 *         warnings: {
	 *         },
	 *     }
	 *
	 * An example report of perfect validation. Expressed as JSON:
	 *     {
	 *         errors: {
	 *         },
	 *         warnings: {
	 *         },
	 *     }
	 *
	 * There are helper functions available to manage the report as validation proceeds. In your implementation of
	 * validate(), set up your report by calling createEmptyReport(), then implement your validation logic and call
	 * addError() and/or addWarning() whenever you need to add an content to the report. These helpers will take care of
	 * ensuring the report's structure is correct.
	 *
	 * @param $object \StdClass The object to validate.
	 *
	 * @return \StdClass A validation report.
	 */
	public abstract function validate(\StdClass $object): \StdClass;
}
