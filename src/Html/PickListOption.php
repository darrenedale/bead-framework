<?php
/**
 * Defines the _PickListOption_ class.
 *
 * ### Changes
 * - (2019-01) First version of this file.
 *
 * @file PickListOption.php
 * @author Darren Edale
 * @version 0.9.1 */

namespace Equit\Html ;

/**
 * A class representing a single option in a pick list.
 *
 * Each pick list has a number of available options. Each option is represented by a single instance of this class.
 *
 * _PickListOption_ objects have a value and a display text. The display text is what the user sees for the option while
 * the value is what is submitted with the form data if the option is selected by the user. They also have an optional
 * tooltip, which is the tooltip displayed for the option if the output type supports it.
 *
 * Objects of this class are used internally by _PickList_ objects. You can use them directly to add options to pick
 * lists if you wish, but it is recommended (and almost always more convenient) to provide the string texts and values
 * to the PickList object rather than encumber your code with calls to the PickListOption constructor.
 *
 * @class PickListOption
 */
class PickListOption {
	/** @var string|null The value for the option. */
	private $m_value = null;

	/** @var string|null The display text for the option. */
	private $m_displayText = null;

	/** @var string|null The tooltip text for the option. */
	private $m_tooltip = null;

	/**
	 * Create a new PickListOption object.
	 *
	 * The constructor is the only place where the value and display text can be
	 * set. Once they are set here, they are fixed for the lifetime of the
	 * object.
	 *
	 * If the display text is not provided, the value is used as the display
	 * text.
	 *
	 * @param $value string The value for the option.
	 * @param $displayText string _optional_ The display text for the option.
	 * @param $tooltip string _optional_ The tooltip text for the option.
	 */
	public function __construct(?string $value, ?string $displayText = null, ?string $tooltip = null) {
		$this->m_value       = $value;
		$this->m_displayText = $displayText;
		$this->m_tooltip     = $tooltip;
	}

	/**
	 * Check whether the pick list option is valid.
	 *
	 * A pick list option is valid if it has a non-empty string for its value.
	 *
	 * @return bool _true_ if the option is valid, _false_ otherwise.
	 */
	public function isValid(): bool {
		return !empty($this->m_value);
	}

	/**
	 * Fetch the option's value.
	 *
	 * @return string The value, or _null_ if no value is set.
	 */
	public function value(): ?string {
		return $this->m_value;
	}

	/**
	 * Fetch the option's display text.
	 *
	 * If the option has no explicit display text, the option's value is returned as its display text. This method,
	 * therefore, always returns the text to be used for display.
	 *
	 * @return string The display text, or _null_ if no display text is available.
	 */
	public function displayText(): ?string {
		return $this->m_displayText ?? $this->m_value;
	}

	/**
	 * Fetch the option's tooltip.
	 *
	 * @return string The tooltip text, or _null_ if no tooltip text is available.
	 */
	public function tooltip(): ?string {
		return $this->m_tooltip;
	}

	/**
	 * Check whether two pick list options are equal.
	 *
	 * Two options are considered equal if they have identical values and display texts as provided by the
	 * _value()_ and
	 * _displayText()_ methods.
	 *
	 * ### Note
	 * Tooltips are not considered when assessing the equality of two items.
	 *
	 * @param $other PickListOption The option to compare to this one.
	 *
	 * @return bool _true_ if this option and the other option are equal, _false_ otherwise.
	 */
	public function equals(PickListOption $other) {
		return $this === $other || ($this->value() == $other->value() && $this->displayText() == $other->displayText());
	}

	/**
	 * Fetch a string representation of the option.
	 *
	 * The string representation is the HTML for the option. This is not intended for use directly in a page, it is
	 * just a convenient way to represent the option. It contains no attributes other than the _value_ attribute,
	 * and is not guaranteed to be correctly encoded or to be valid HTML as neither the value nor the display text
	 * is escaped.
	 *
	 * @return string The option as a string.
	 */
	public function __toString(): string {
		return "<option value=\"" . $this->value() . "\" title=\"" . $this->tooltip() . "\">" . $this->displayText() . "</option>";
	}
}
