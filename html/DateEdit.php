<?php
declare(strict_types=1);

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\Name;
use Equit\Html\Placeholder;
use Equit\Html\Tooltip;
use Equit\Html\PageElement;

/**
 * Defines the DateEdit class.
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
 * A date editor for inclusion in forms.
 *
 * The date editor currently uses the HTML5 _date_ input type. User agents that do not support this element _should_
 * degrade gracefully and present a simple text input. In future this class may include a shim for non-supporting
 * browsers.
 *
 * The code that receives the date entered by the user must validate that the provided date matches the format
 * _YYYY-MM-DD_. The reliance on the HTML _date_ input type means that in user agents that do not support it, any
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
class DateEdit extends PageElement {
	use Name;
	use Placeholder;
	use Tooltip;

	const OutputFormat = "Y-m-d";

	private static $s_dateEditAttributeNames = ["value", "name", "placeholder", "title", "min", "max", "step"];

	/**
	 * Create a new date edit widget.
	 *
	 * The ID parameter is optional. By default, a widget with no ID is created.
	 *
	 * @param $id string _optional_ The ID of the date edit widget.
	 */
	public function __construct($id = null) {
		parent::__construct($id);

		foreach(self::$s_dateEditAttributeNames as $name) {
			$this->setAttribute($name, null);
		}
	}

	/**
	 * Fetch the date contained in date edit widget.
	 *
	 * @return \DateTime The date, or _null_ if no date is set.
	 */
	public function date(): ?\DateTime {
		$date = $this->attribute("value");

		if(!is_string($date)) {
			return null;
		}

		try {
			$date = new \DateTime($date);
		}
		catch(\Exception $e) {
			AppLog::error("invalid date", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $date;
	}

	/**
	 * Set the date contained in date edit widget.
	 *
	 * If the date provided is a string, it must be formatted as _YYYY-MM-DD_. It will be converted internally into a
	 * _DateTime_ object.
	 *
	 * The date provided can be _null_ to unset the date currently contained in the widget.
	 *
	 * @param $date \DateTime|string The date.
	 *
	 * @return bool _true_ if the date was set, _false_ otherwise.
	 */
	public function setDate($date): bool {
		if($date instanceof \DateTime) {
			$date = $date->format("Y-m-d");
		}

		if(is_string($date) || is_null($date)) {
			return $this->setAttribute("value", $date);
		}

		AppLog::error("invalid date", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the earliest date the user may enter in the widget.
	 *
	 * @return \DateTime The earliest date, or _null_ if no earliest date is set.
	 */
	public function min(): ?\DateTime {
		$min = $this->attribute("min");

		if(!is_string($min)) {
			return null;
		}

		try {
			$min = new \DateTime($min);
		}
		catch(\Exception $e) {
			AppLog::error("invalid minimum date", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $min;
	}

	/**
	 * Set the earliest date the user may enter in date edit widget.
	 *
	 * If the date provided is a _string_, it must be formatted as _YYYY-MM-DD_. It will be converted internally into a
	 * _DateTime_ object.
	 *
	 * The date provided can be _null_ to unset the earliest date the user may enter into the widget.
	 *
	 * ### Note
	 * The earliest date is only supported if the user agent fully supports the HTML5 _date_ input type.
	 *
	 * @param $min \DateTime|string The earliest date.
	 *
	 * @return bool _true_ if the earliest date was set, _false_ otherwise.
	 */
	public function setMin($min): bool {
		if($min instanceof \DateTime) {
			$min = $min->format("Y-m-d");
		}

		if(is_string($min) || is_null($min)) {
			return $this->setAttribute("min", $min);
		}

		AppLog::error("invalid minimum date", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the latest date the user may enter in the widget.
	 *
	 * @return \DateTime The latest date, or _null_ if no latest date is set.
	 */
	public function max(): ?\DateTime {
		$max = $this->attribute("max");

		if(!is_string($max)) {
			return null;
		}

		try {
			$max = new \DateTime($max);
		}
		catch(\Exception $e) {
			AppLog::error("invalid maximum date", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $max;
	}

	/**
	 * Set the latest date the user may enter in date edit widget.
	 *
	 * If the date provided is a _string_, it must be formatted as _YYYY-MM-DD_. It will be converted internally into a
	 * _DateTime_ object.
	 *
	 * The date provided can be _null_ to unset the latest date the user may enter into the widget.
	 *
	 * ### Note
	 * The latest date is only supported if the user agent fully supports the HTML5 _date_ input type.
	 *
	 * @param $max \DateTime|string The latest date.
	 *
	 * @return bool _true_ if the latest date was set, _false_ otherwise.
	 */
	public function setMax($max): bool {
		if($max instanceof \DateTime) {
			$max = $max->format("Y-m-d");
		}

		if(is_string($max) || is_null($max)) {
			return $this->setAttribute("max", $max);
		}

		AppLog::error("invalid maximum date", __FILE__, __LINE__, __FUNCTION__);
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
		return "<input type=\"date\"" . $this->emitAttributes() . " />";
	}
}
