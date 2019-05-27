<?php
declare(strict_types=1);

namespace Equit\Html;

use DateTime;
use Equit\AppLog;
use Exception;

/**
 * Defines the TimeEdit class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\PageElement.php
 *
 * ### Changes
 * - (2018-09) Uses traits for common HTML attributes.
 * - (2018-09) Uses string type hints.
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
 * - (2013-12-10) First version of this file.
 *
 * @file DateEdit.php
 * @author Darren Edale
 * @version 1.1.2
 * @date Jan 2018
 * @package libequit
 */

/**
 * A time editor for inclusion in forms.
 *
 * The date editor currently uses the HTML5 _time_ input type. User agents that do not support this element _should_
 * degrade gracefully and present a simple text input. In future this class may include a shim for non-supporting
 * browsers.
 *
 * The code that receives the time entered by the user must validate that the provided time matches the format
 * _HH:MM_. The reliance on the HTML _time_ input type means that in user agents that do not support it, any
 * content can be entered. It's good practice to do such validation anyway (and you're asking for trouble if you don't),
 * but in this case it is a necessity.
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
 * @class DateEdit
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
class TimeEdit extends PageElement {
	use Name;
	use Placeholder;
	use Tooltip;

	/**
	 * The output format used when generating HTML representations of time values.
	 */
	const OutputFormat = "H:i";

	/**
	 * @var array The attributes supported by time edits.
	 */
	private static $s_timeEditAttributeNames = ["value", "name", "placeholder", "title", "min", "max", "step"];

	/**
	 * Create a new date edit widget.
	 *
	 * The ID parameter is optional. By default, a widget with no ID is created.
	 *
	 * @param $id string _optional_ The ID of the date edit widget.
	 */
	public function __construct($id = null) {
		parent::__construct($id);

		foreach(self::$s_timeEditAttributeNames as $name) {
			$this->setAttribute($name, null);
		}
	}

	/**
	 * Fetch the time contained in the time edit widget.
	 *
	 * @return \DateTime The time, or _null_ if no time is set.
	 */
	public function time(): ?DateTime {
		$time = $this->attribute("value");

		if(!is_string($time)) {
			return null;
		}

		try {
			$time = new DateTime($time);
		}
		catch(Exception $e) {
			AppLog::error("invalid time", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $time;
	}

	/**
	 * Set the time contained in time edit widget.
	 *
	 * If the time provided is a string, it must be formatted as _HH:MM_. It will be converted internally into a
	 * _DateTime_ object.
	 *
	 * The time provided can be _null_ to unset the time currently contained in the widget.
	 *
	 * @param $time \DateTime|string The time.
	 *
	 * @return bool _true_ if the time was set, _false_ otherwise.
	 */
	public function setTime($time): bool {
		if($time instanceof DateTime) {
			$time = $time->format(self::OutputFormat);
		}

		if(is_string($time) || is_null($time)) {
			$this->setAttribute("value", $time);
			return true;
		}

		AppLog::error("invalid time", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the earliest time the user may enter in the widget.
	 *
	 * @return \DateTime The earliest time, or _null_ if no earliest time is set.
	 */
	public function min(): ?DateTime {
		$min = $this->attribute("min");

		if(!is_string($min)) {
			return null;
		}

		try {
			$min = new DateTime($min);
		}
		catch(Exception $e) {
			AppLog::error("invalid minimum time", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $min;
	}

	/**
	 * Set the earliest time the user may enter in time edit widget.
	 *
	 * If the time provided is a _string_, it must be formatted as _HH:MM_. It will be converted internally into a
	 * _DateTime_ object.
	 *
	 * The time provided can be _null_ to unset the earliest time the user may enter into the widget.
	 *
	 * ### Note
	 * The earliest time is only supported if the user agent fully supports the HTML5 _time_ input type.
	 *
	 * @param $min \DateTime|string The earliest time.
	 *
	 * @return bool _true_ if the earliest time was set, _false_ otherwise.
	 */
	public function setMin($min): bool {
		if($min instanceof DateTime) {
			$min = $min->format(self::OutputFormat);
		}

		if(is_string($min) || is_null($min)) {
			$this->setAttribute("min", $min);
			return true;
		}

		AppLog::error("invalid minimum time", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the latest time the user may enter in the widget.
	 *
	 * @return \DateTime The latest time, or _null_ if no latest time is set.
	 */
	public function max(): ?DateTime {
		$max = $this->attribute("max");

		if(!is_string($max)) {
			return null;
		}

		try {
			$max = new DateTime($max);
		}
		catch(Exception $e) {
			AppLog::error("invalid maximum time", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $max;
	}

	/**
	 * Set the latest time the user may enter in time edit widget.
	 *
	 * If the time provided is a _string_, it must be formatted as _HH:MM_. It will be converted internally into a
	 * _DateTime_ object.
	 *
	 * The time provided can be _null_ to unset the latest time the user may enter into the widget.
	 *
	 * ### Note
	 * The latest time is only supported if the user agent fully supports the HTML5 _time_ input type.
	 *
	 * @param $max \DateTime|string The latest time.
	 *
	 * @return bool _true_ if the latest time was set, _false_ otherwise.
	 */
	public function setMax($max): bool {
		if($max instanceof DateTime) {
			$max = $max->format(self::OutputFormat);
		}

		if(is_string($max) || is_null($max)) {
			$this->setAttribute("max", $max);
			return true;
		}

		AppLog::error("invalid maximum time", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Generate the HTML for the widget.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return "<input type=\"time\"" . $this->emitAttributes() . " />";
	}
}
