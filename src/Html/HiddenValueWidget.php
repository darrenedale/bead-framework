<?php

/**
 * Defines the HiddenValueWidget class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\PageElement.php
 * - classes/equit/HtmlName.php
 *
 * ### Changes
 * - (2018-09) Uses traits for common HTML attributes.
 * - (2018-09) Uses string type hints.
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
 * - (2013-12-10) First version of this file.
 *
 * @file HiddenValueWidget.php
 * @author Darren Edale
 * @version 0.9.2
 * @package libequit
 * @version 0.9.2 */

namespace Equit\Html;

use Equit\Html\HasNameAttribute;
use Equit\Html\PageElement;

/**
 * A widget for inclusion in forms that contains a fixed, hidden
 * value.
 *
 * This class provides a means of placing hiddent values in forms to be
 * submitted with the rest of the form data. This is useful when a form needs
 * to submit a fixed value that the user cannot change.
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
 * @class HiddenValueWidget
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
class HiddenValueWidget extends PageElement {
	use HasNameAttribute;

	private static $s_hiddenValueWidgetAttributeNames = ["value", "name"];

	/**
	 * Create a new HiddenValueWidget.
	 *
	 * The provided ID can be _null_ to create a widget without an ID.
	 *
	 * @param $id ?string The ID for the widget.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);

		foreach(self::$s_hiddenValueWidgetAttributeNames as $name) {
			$this->setAttribute($name, null);
		}
	}

	/**
	 * Fetch the value of the data submitted by the widget.
	 *
	 * @return string The value, or _null_ if no value has been set.
	 */
	public function value(): ?string {
		return $this->attribute("value");
	}

	/**
	 * Set the name of the data submitted by the widget.
	 *
	 * @param $text string The value to use.
	 */
	public function setValue(?string $text): void {
		$this->setAttribute('value', $text);
	}

	/**
	 * Generate the HTML for the widget.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		$classNames = $this->classNames();
		$this->addClassName("hidden_widget");
		$ret = "<input type=\"hidden\" " . $this->emitAttributes() . " />";
		$this->setClassNames($classNames);
		return $ret;
	}
}
