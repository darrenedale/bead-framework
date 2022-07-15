<?php

namespace Equit\Html;

/**
 * A widget for inclusion in forms that contains a fixed, hidden
 * value.
 *
 * This class provides a means of placing hiddent values in forms to be
 * submitted with the rest of the form data. This is useful when a form needs
 * to submit a fixed value that the user cannot change.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class HiddenValueWidget extends Element {
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
