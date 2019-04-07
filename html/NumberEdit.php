<?php

/**
 * Defines the NumberEdit class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\PageElement.php
 *
 * ### Changes
 * - (2017-05) Removed superfluous class constant. Updated documentation.
 *   Migrated to `[]` syntax from array().
 * - (2013-12-10) First version of this file.
 *
 * @file NumberEdit.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\Name;
use Equit\Html\Placeholder;
use Equit\Html\Tooltip;
use Equit\Html\PageElement;

/**
 * A number editor for inclusion in forms.
 *
 * The number editor currently uses the HTML5 _number_ input type. User agents that do not support this element _should_
 * degrade gracefully and present a simple text input. In future this class may include a shim for browsers that don't
 * support the _number_ element.
 *
 * The code that receives the number entered by the user must validate that the provided value is indeed a number. The
 * reliance on the HTML _number_ input type means that in user agents that do not support it, any content can be
 * entered. It's good practice to do such validation anyway (and you're asking for trouble if you don't), but in this
 * case it is a necessity.
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
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 *
 * @class NumberEdit
 * @author Darren Edale
 * @package libequit
 */
class NumberEdit extends PageElement {
	use Name;
	use Placeholder;
	use Tooltip;

	/** The HTML attributes supported by NumberEdit objects. */
	private static $s_numberEditAttributeNames = ["value", "name", "placeholder", "title", "min", "max", "step"];

	/**
	 * Create a new number edit widget.
	 *
	 * @param $id string _optional_ The ID of the number edit widget.
	 *
	 * By default, a widget with no ID is created.
	 */
	public function __construct($id = null) {
		parent::__construct($id);

		foreach(self::$s_numberEditAttributeNames as $name) {
			$this->setAttribute($name, null);
		}
	}

	/**
	 * Fetch the number contained in number edit widget.
	 *
	 * @return float The number, or _null_ if no number is set.
	 */
	public function number(): ?float {
		$v = $this->attribute("value");

		if(!is_numeric($v)) {
			AppLog::error("invalid number value", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return doubleval($v);
	}


	/**
	 * Fetch the number contained in number edit widget.
	 *
	 * @return int The number, or _null_ if no number is set.
	 */
	public function integer(): ?int {
		$v = $this->attribute("value");

		if(!is_numeric($v)) {
			AppLog::error("invalid number value", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return intval($v);
	}

	/**
	 * Set the number contained in number edit widget to a double.
	 *
	 * The number provided can be _null_ to unset the number currently contained in the widget.
	 *
	 * @param $v double The number.
	 *
	 * @return void.
	 */
	public function setNumber(?float $v): void {
		if(isset($v)) {
			$this->setAttribute("value", "$v");
		}
		else {
			$this->setAttribute("value", null);
		}
	}

	/**
	 * Set the number contained in number edit widget to an integer.
	 *
	 * The number provided can be _null_ to unset the number currently contained in the widget.
	 *
	 * @param $v int The number.
	 *
	 * @return void.
	 */
	public function setInteger(?int $v) {
		if(isset($v)) {
			$this->setAttribute("value", "$v");
		}
		else {
			$this->setAttribute("value", null);
		}
	}

	/**
	 * Fetch the smallest number the user may enter in the widget.
	 *
	 * @return double The smallest number, or _null_ if no smallest number is set.
	 */
	public function min(): ?float {
		$v = $this->attribute("min");

		if(isset($v)) {
			return doubleval($v);
		}

		return null;
	}

	/**
	 * Fetch the smallest number the user may enter in the widget.
	 *
	 * @return int The smallest number, or _null_ if no smallest number
	 * is set.
	 */
	public function integerMin(): ?int {
		$v = $this->attribute("min");

		if(isset($v)) {
			return intval($v);
		}

		return null;
	}

	/**
	 * Set the smallest number the user may enter in number edit widget.
	 *
	 * The min provided can be _null_ to unset the minimum value.
	 *
	 * ## Note
	 * The min is only supported if the user agent fully supports the HTML5 _number_ input type.
	 *
	 * @param $min double The smallest number.
	 *
	 * @return void.
	 */
	public function setMin(?float $min): void {
		if(isset($min)) {
			$this->setAttribute("min", "$min");
		}

		$this->setAttribute("min", null);
	}

	/**
	 * Fetch the largest number the user may enter in the widget.
	 *
	 * @return double The largest number, or _null_ if no largest number is set.
	 */
	public function max(): ?float {
		$v = $this->attribute("max");

		if(isset($v)) {
			return doubleval($v);
		}

		return null;
	}

	/**
	 * Fetch the largest number the user may enter in the widget.
	 *
	 * @return int The largest number, or _null_ if no largest number is set.
	 */
	public function integerMax(): ?int {
		$v = $this->attribute("max");

		if(isset($v)) {
			return intval($v);
		}

		return null;
	}

	/**
	 * Set the largest number the user may enter in number edit widget.
	 *
	 * The max provided can be _null_ to unset the max.
	 *
	 * ### Note
	 * The max is only supported if the user agent fully supports the HTML5 _number_ input type.
	 *
	 * @param $max int, `double`, string The largest number.
	 *
	 * @return void.
	 */
	public function setMax($max): void {
		if(isset($max)) {
			$this->setAttribute("max", "$max");
		}

		$this->setAttribute("max", null);
	}

	/**
	 * Fetch the step by which the number is increased/decresed with
	 * the increase/decrease buttons.
	 *
	 * @return double The step, or _null_ if no step is set.
	 */
	public function step(): ?float {
		$v = $this->attribute("step");

		if(isset($v)) {
			return doubleval($v);
		}

		return null;
	}

	/**
	 * Fetch the step by which the number is increased/decresed with
	 * the increase/decrease buttons.
	 *
	 * This is a convenience method to guarantee the return type is an integer, regardless of how it was set.
	 *
	 * @return int The step, or _null_ if no step is set.
	 */
	public function integerStep(): ?int {
		$v = $this->attribute("step");

		if(isset($v)) {
			return intval($v);
		}

		return null;
	}

	/**
	 * Set the step by which the number is increased/decreased with the increase/decrease buttons.
	 *
	 * The step provided can be _null_ to unset the step.
	 *
	 * ### Note
	 * The step is only supported if the user agent fully supports the HTML5 _number_ input type.
	 *
	 * @param $step double The step number.
	 *
	 * @return void.
	 */
	public function setStep(?float $step): void {
		if(isset($step)) {
			$this->setAttribute("step", "$step");
		}

		$this->setAttribute("step", null);
	}

	/**
	 * Generate the HTML for the widget.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return "<input type=\"number\"" . $this->emitAttributes() . " />";
	}
}
