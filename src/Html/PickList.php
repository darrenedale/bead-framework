<?php

/**
 * Defines the _PickList_ class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/Page.php
 * - classes/equit/PageElement.php
 *
 * ### Changes
 * - (2017-05) Updated documentation. Migrated array literals to `[]` syntax.
 * - (2013-12-10) First version of this file.
 *
 * @file PickList.php
 * @author Darren Edale
 * @version 0.9.2 */

namespace Equit\Html;

use Equit\AppLog;

/**
 * A pick list for inclusion in forms.
 *
 * This class represents a widget on a form that enables the user to choose from items in a list. It has two modes of
 * operation:
 * - *single-select* where the user must choose exactly one option from the list
 * - *multi-select* where the user can choose any number of options from the list, including choosing none of them.
 *
 * Upon form submission, all of the user's chosen options are submitted with the form data. If the user chooses no
 * options, the data is omitted from the form data. For items in the list, the display text is distinct from the value
 * represented in the list. That is, each item in the list has a display text and a value. The display text is shown to
 * the user, and the value is what is submitted with the form data. When adding items to the list, for convenience if
 * any item is added without a display text, the value will be used for the display text. Every item added to the list
 * of options is required to have a value.
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
 * @class PickList
 * @author Darren Edale
 * @package libequit
 */
class PickList extends Element {
	// import traits
	use HasNameAttribute;
	use HasTooltip;

	/** @var int Type constant for single-select pick lists. */
	public const SingleSelect = 1;

	/** @var int Type constant for multi-select pick lists. */
	public const MultiSelect = 2;

	/**
	 * @var array[string] The attributes supported by pick lists.
	 * @const
	 */
	private static $s_pickListAttributeNames = ["name", "title"];

	/** @var int The pick list type. */
	private $m_type = self::SingleSelect;

	/** @var array[string] The default selected options. */
	private $m_selections = [];

	/** @var array[PickListOption] The full set of available options. */
	private $m_options = [];

	/**
	 * Create a new pick list.
	 *
	 * Both parameters are optional. By default a single-select pick list with no ID will be created.
	 *
	 * @param $type int _optional_ The pick list type.
	 * @param $id string|null _optional_ The unique ID for the pick list.
	 */
	public function __construct(int $type = self::SingleSelect, ?string $id = null) {
		parent::__construct($id);

		foreach(self::$s_pickListAttributeNames as $name) {
			$this->setAttribute($name, null);
		}

		$this->setType($type);
	}

	/**
	 * Fetch the pick list type.
	 *
	 * The type will be either _SingleSelect_ or _MultiSelect_.
	 *
	 * @return int The type.
	 */
	public function type(): int {
		return $this->m_type;
	}

	/**
	 * Set the pick list type.
	 *
	 * The type must be either _SingleSelect_ or _MultiSelect_.
	 *
	 * @param $type int The type.
	 *
	 * @return bool _true_ if the type was set, _false_ otherwise.
	 */
	public function setType(int $type): bool {
		if($type === self::SingleSelect || $type == self::MultiSelect) {
			$this->m_type = $type;
			return true;
		}

		AppLog::error("invalid type", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the list of options.
	 *
	 * @return array[PickListOption] The options.
	 */
	public function options(): array {
		return $this->m_options;
	}

	/**
	 * Add an option to the pick list.
	 *
	 * If the option provided is a _PickListOption_ object, the option that is added. It is not cloned, so any
	 * changes you make to the object after it has been added will be reflected in the option in the list. Be sure
	 * to pass new instances to add further options rather than modifying and adding the same object, otherwise you
	 * will end up with a list containing identical items. The provided display text parameter in this case will be
	 * ignored.
	 *
	 * If the option provided is a _string_, it is used as the value for a new _PickListOption_ object, and the
	 * provided display text is used.
	 *
	 * @param $option PickListOption|string The option to add.
	 * @param $displayText string The display text to use.
	 * @param $tooltip string The tooltip to use for the item.
	 *
	 * @return bool _true_ if the option was added, _false_ otherwise.
	 */
	public function addOption($option, ?string $displayText = null, ?string $tooltip = null): bool {
		if(is_string($option)) {
			$option = new PickListOption($option, $displayText, $tooltip);
		}

		if(!$option instanceof PickListOption) {
			AppLog::error("invalid option", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!$option->isValid()) {
			AppLog::error("invalid option", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_options[] = $option;
		return true;
	}

	/**
	 * Remove an option from the list.
	 *
	 * The option and display text parameters are handled the same way as _addOption()_ handles them.
	 *
	 * This method looks for a matching option in the pick list's set of options and removes any that are considered
	 * equal. The _PickListOption::equals()_ method is used to determine equality.
	 *
	 * @param $option PickListOption|string The option to remove.
	 * @param $displayText string The display text of the option to remove.
	 *
	 * @return bool _true_ if all matching options were removed (even if none were found), _false_ on error.
	 */
	public function removeOption($option, ?string $displayText = null): bool {
		if(is_string($option)) {
			$option = new PickListOption($option, $displayText);
		}

		if(!$option instanceof PickListOption) {
			AppLog::error("invalid option", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!$option->isValid()) {
			AppLog::error("invalid option", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		for($i = 0; $i < count($this->m_options); ++$i) {
			$myOption = $this->m_options[$i];

			if($myOption->equals($option)) {
				array_splice($this->m_options, $i, 1);
				--$i;
			}
		}

		return true;
	}

	/**
	 * Set all the options for the pick list.
	 *
	 * The set of options must be an array, but it can contain a mixture of value => display_text pairs and
	 * _PickListOption_ objects. For value => display_text pairs, the key of the array element is used as the
	 * option
	 * value and the value of the array element is used as the display text. If the display text is anything other
	 * than a _string_, the value is also used as the display text.
	 *
	 * This method is a convenience to enable bulk setup of all the options in a pick list if the creator has them
	 * available in an array. It offers no speed benefit over calling _addOption()_ for each item.
	 *
	 * The options will only be set if all of the elements of the provided array are valid. If one or more is not
	 * valid, the pick list's set of options will be unmodified.
	 *
	 * @param $options array[string=>string,mixed=>PickListOption] The options to set.
	 *
	 * @return bool _true_ if the options were set, _false_ otherwise.
	 */
	public function setOptions(?array $options): bool {
		if(is_null($options)) {
			$options = [];
		}

		if(!is_array($options)) {
			AppLog::error("invalid options", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$myOptions = [];

		foreach($options as $value => $option) {
			if(is_string($option)) {
				$option = new PickListOption((is_string($value) ? $value : $option), $option);
			}

			if(!($option instanceof PickListOption)) {
				AppLog::error("one or more invalid options", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}

			$myOptions[] = $option;
		}

		$this->m_options = $myOptions;
		return true;
	}

	/**
	 * Fetch the list of selections.
	 *
	 * The selections are the options that are initially selected. Only the values of the selected options are provided.
	 *
	 * For single-select lists, an array of one item is provided.
	 *
	 * @return array[string] The selected options.
	 */
	public function selections(): array {
		$this->m_selections = array_unique($this->m_selections);
		return $this->m_selections;
	}

	/**
	 * Set a single selection in the list.
	 *
	 * @param $selection string The value of the option to select.
	 *
	 * This method discards the existing set of selections and selects just one item in the list. The selection provided
	 * can be _null_ to unset the existing selection.
	 *
	 * @return void.
	 */
	public function setSelection(?string $selection): void {
		if(isset($selection)) {
			$this->setSelections([$selection]);
			return;
		}

		$this->setSelections([]);
	}

	/**
	 * Add a selection to the pick list's set of selections.
	 *
	 * @param $selection string The value of the option to add to the selection.
	 *
	 * @return void.
	 */
	public function addSelection(string $selection): void {
		$this->m_selections[] = $selection;
	}

	/**
	 * Remove a selection from the pick list's set of selections.
	 *
	 * @param $selection string The value of the option to remove from the selection.
	 *
	 * @return void.
	 */
	public function removeSelection(string $selection): void {
		if(in_array($selection, $this->m_selections)) {
			$this->m_selections = array_unique($this->m_selections);
			array_splice($this->m_selections, array_search($selection, $this->m_selections), 1);
		}
	}

	/**
	 * Set the selections for the list.
	 *
	 * @param $selections array[string] The set of option values to select.
	 *
	 * This method discards the existing set of selections and selects a new set. If any of the provided selections
	 *     is not valid, the set of selections is unmodified. Invalid in this instance means that the selection is
	 *     valid for an option value regardless of whether that option value is actually present in the list of
	 *     options.
	 *
	 * Any selections that are not present as option values in the pick list when the HTML is generated are simply
	 * ignored.
	 *
	 * @return bool _true_ if the selection was set, _false_ otherwise.
	 */
	public function setSelections(array $selections) {
		foreach($selections as $s) {
			if(!is_string($s)) {
				AppLog::error("one or more invalid selections", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}
		}

		$this->m_selections = $selections;
		return true;
	}

	/**
	 * Generate the HTML for a single-select pick list.
	 *
	 * This is an internal helper function that is used by html() to generate
	 * the content when the pick list's type is SingleSelect.
	 *
	 * @return string The HTML.
	 */
	private function emitSingleSelect(): string {
		$ret       = "<select" . $this->emitAttributes() . " size=\"1\">";
		$selection = (0 < count($this->m_selections) ? $this->m_selections[0] : null);

		foreach($this->m_options as $option) {
			$ret     .= "\n<option value=\"" . html($option->value()) . "\"";
			$tooltip = $option->tooltip();

			if(!empty($tooltip)) {
				$ret .= " title=\"" . html($tooltip) . "\"";
			}

			if($selection && $option->value() === $selection) {
				$ret .= " selected=\"selected\"";
			}

			$ret .= ">" . html($option->displayText()) . "</option>";
		}

		return "$ret\n</select>";
	}

	/**
	 * Generate the HTML for a multi-select pick list.
	 *
	 * This is an internal helper function that is used by html() to generate
	 * the content when the pick list's type is MultiSelect.
	 *
	 * @return string The HTML.
	 */
	private function emitMultiSelect(): string {
		$name     = $this->name();
		$htmlName = html($name);
		$this->setName(null);

		$classNames = $this->classNames();
		$this->addClassName("set_widget");

		$id = $this->id();

		if(empty($id)) {
			$id = self::generateUid();
		}

		$htmlId = html($id);

		$ret         = "<ul" . $this->emitAttributes() . ">";
		$optionIndex = 1;

		foreach($this->m_options as $option) {
			$optionId = "{$htmlName}_{$htmlId}_option_{$optionIndex}";
			$ret      .= "\n<li><input type=\"checkbox\" name=\"{$htmlName}[]\" value=\"" . html($option->value()) . "\" id=\"$optionId\"";

			$tooltip = $option->tooltip();

			if(!empty($tooltip)) {
				$ret .= " title=\"" . html($tooltip) . "\"";
			}

			if(in_array($option->value(), $this->m_selections)) {
				$ret .= " checked=\"checked\"";
			}

			$ret .= " /><label for=\"$optionId\"";

			if(!empty($tooltip)) {
				$ret .= " title=\"" . html($tooltip) . "\"";
			}

			$ret .= ">" . html($option->displayText()) . "</label></li>";
			++$optionIndex;
		}

		$this->setName($name);
		$this->setClassNames($classNames);
		return "$ret\n</ul>";
	}

	/**
	 * Generate the HTML for the pick list.
	 *
	 * This method generates UTF-8 encoded XHTML10 Strict.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		switch($this->m_type) {
			case self::SingleSelect:
				return $this->emitSingleSelect();

			case self::MultiSelect:
				return $this->emitMultiSelect();
		}

		AppLog::error("invalid pick list type: {$this->m_type}", __FILE__, __LINE__, __FUNCTION__);
		trigger_error(tr("Internal error (%1).", __FILE__, __LINE__, "ERR_PICKLIST_INVALID_TYPE"), E_USER_ERROR);
	}
}
