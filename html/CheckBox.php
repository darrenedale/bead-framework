<?php
declare(strict_types=1);

namespace Equit\Html;

use Equit\Html\FormElementDisabled;
use Equit\Html\Name;
use Equit\Html\Tooltip;
use Equit\Html\PageElement;

/**
 * Defines the CheckBox class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\PageElement.php
 *
 * ### Todo
 * - Event attributes as a trait.
 *
 * ### Changes
 * - (2018-09) Uses traits for common HTML attributes.
 * - (2018-09) Uses string type hints.
 * - (2017-05) Updated documentation. Migrated to use `[]` instead of array().
 * - (2013-12-10) First version of this file.
 *
 * @file CheckBox.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

/**
 * A check box for inclusion in forms.
 *
 * This class represents a widget on a form that enables the user to tick or untick an option box.
 *
 * Upon form submission, a value is submitted if the user ticked the box; if not, no value is submitted (i.e. the
 * checkbox's name is omitted from the URL parameters or POST data.
 *
 * The check box can optionally have a label. To give it a label call _setLabel()_. If it has a label, the position of
 * the label can also be set either to the left of the checkbox or to its right. Use _setLabelPosition()_ to position
 * the label. To turn off the label, whether it's set or not, set the label position to _NoLabel_ (this is the default
 * setting).
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This plugin provides the following API functions:
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
 * @class CheckBox
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
class CheckBox extends PageElement {
	use Name;
	use Tooltip;
	use FormElementDisabled;

	const NoLabel = 0;
	const LabelLeft = 1;
	const LabelRight = 2;

	private static $s_checkBoxAttributeNames = ['name', 'title', 'value', 'onclick'];

	/** @var bool Whether or not the checkbox is initially checked. */
	private $m_checked = false;

	/** @var ?string The label for the checkbox. */
	private $m_label = null;

	/** @var int The position of the label. */
	private $m_labelPosition = self::NoLabel;

	/**
	 * Create a new check box.
	 *
	 * @param $id string|null _optional_ The unique ID for the check box.
	 *
	 * By default an unchecked check box with no ID and no label will be created.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);

		foreach(self::$s_checkBoxAttributeNames as $name) {
			$this->setAttribute($name, null);
		}

		$this->setAttribute("value", "1");
	}

	/** Fetch the label for the check box.
	 *
	 * @return string|null The label, or _null_ if none has been set.
	 */
	public function label(): ?string {
		return $this->m_label;
	}

	/** Set the label for the check box.
	 *
	 * @param $label string|null The label
	 *
	 * @return void.
	 */
	public function setLabel(?string $label): void {
		$this->m_label = $label;
	}

	/** Fetch the label position for the check box.
	 *
	 * The position is guaranteed to be one of the class label position
	 * constants.
	 *
	 * @return int The label position, or _null_ if none has been set.
	 */
	public function labelPosition(): int {
		return $this->m_labelPosition;
	}

	/** Set the label position for the check box.
	 *
	 * The provided position must be one of the class label position constants.
	 *
	 * @param $labelPosition int The label position
	 *
	 * @return bool _true_ if the label position was set, _false_ if an invalid label position was supplied.
	 */
	public function setLabelPosition(int $labelPosition): bool {
		switch($labelPosition) {
			case self::NoLabel:
			case self::LabelLeft:
			case self::LabelRight:
				$this->m_labelPosition = intval($labelPosition);
				return true;
		}

		return false;
	}

	/** Fetch the initial checked state for the check box.
	 *
	 * @return bool The initial checked state.
	 */
	public function checked(): bool {
		return $this->m_checked;
	}

	/** Set the initial checked stat for the check box.
	 *
	 * @param $checked bool The initial checked state.
	 *
	 * @return void.
	 */
	public function setChecked(bool $checked): void {
		$this->m_checked = $checked;
	}

	/** Set the \b onclick attribute for the checkbox.
	 *
	 * @param $attrValue string The value for the _onclick_ attribute.
	 *
	 * @return void.
	 */
	public function setOnClick(?string $attrValue): void {
		$this->setAttribute("onclick", $attrValue);
	}

	/** Fetch the _onclick_ attribute for the checkbox.
	 *
	 * @return string The value for the _onclick_ attribute, or _null_ if the attribute is not set.
	 */
	public function onClick(): ?string {
		return $this->attribute("onclick");
	}

	/** Generate the HTML for the check box.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		$leftLabel  = "";
		$rightLabel = "";
		$id         = $this->id();
		$label      = $this->label();

		if(is_string($label)) {
			switch($this->labelPosition()) {
				case self::LabelLeft:
					$leftLabel = "<label" . ("" != $id ? " for=\"" . html($id) . "\"" : "") . ">" . html($label) . "</label>&nbsp;";
					break;

				case self::LabelRight:
					$rightLabel = "&nbsp;<label" . ("" != $id ? " for=\"" . html($id) . "\"" : "") . ">" . html($label) . "</label>";
					break;
			}
		}

		return "$leftLabel<input type=\"checkbox\"" . $this->emitAttributes() . ($this->checked() ? " checked=\"checked\"" : "") . " />$rightLabel";
	}
}
