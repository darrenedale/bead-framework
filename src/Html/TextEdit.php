<?php

/**
 * Defines the _TextEdit_ class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\PageElement.php
 * - classes/equit/HtmlName.php
 * - classes/equit/HtmlPlaceholder.php
 * - classes/equit/HtmlTooltip.php
 *
 * ### Changes
 * - (2018-09) Uses html attribute traits.
 * - (2018-09) Uses strict type hints.
 * - (2017-05) Updated documentation. Migrated to `[]` syntax for array literals.
 *   Refactored all the single-line type widget creation code into a single method
 *   that adapts the `type` attribute.
 * - (2016-10-17) Added Email, Url and Search input types.
 * - (2016-10-17) Added support for "autocomplete" attribute.
 * - (2013-12-10) First version of this file.
 *
 * @file TextEdit.php
 * @author Darren Edale
 * @version 0.9.1
 * @version 0.9.1 * @package libequit
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\HasNameAttribute;
use Equit\Html\HasPlaceholderAttribute;
use Equit\Html\HasTooltip;
use Equit\Html\PageElement;

/**
 * A text editor for inclusion in forms.
 *
 * This is a lightweight, general-purpose text editor that allows the user to enter plain text into forms. Objects can
 * be set up to be single-line, multi- line, or for password entry. They can also have an optional placeholder that is
 * put into the widget when it is empty (but is not submitted with the form data if the widget is empty).
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This plugin does not provide any AIO API functions.
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
 * @class TextEdit
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
class TextEdit extends PageElement {
	use HasNameAttribute;
	use HasPlaceholderAttribute;
	use HasTooltip;

	/** @var int Type constant for single-line text edits. */
	const SingleLine = 1;

	/** @var int Type constant for password text edits. */
	const Password = 2;

	/** @var int Type constant for email address text edits. */
	const Email = 3;

	/** @var int Type constant for URL text edits. */
	const Url = 4;

	/** @var int Type constant for search text edits. */
	const Search = 5;

	/** @var int Type constant for multi-line text edits. */
	const MultiLine = 6;

	/** @var array HTML attribute names used by this class. */
	private static $s_textEditAttributeNames = ["value", "name", "placeholder", "title"];

	/** @var int The TextEdit type. */
	private $m_type = self::SingleLine;

	/**
	 * Create a new text edit widget.
	 *
	 * By default, a single-line edit with no ID is created.
	 *
	 * @param $type int _optional_ The type of widget to create.
	 * @param $id string _optional_ The ID of the text edit widget.
	 */
	public function __construct(int $type = self::SingleLine, ?string $id = null) {
		parent::__construct($id);

		foreach(self::$s_textEditAttributeNames as $name) {
			$this->setAttribute($name, null);
		}

		$this->setAutocomplete(false);
		$this->setType($type);
	}

	/**
	 * Fetch the type of the text edit widget.
	 *
	 * The type is guaranteed to be one of the edit type class constants.
	 *
	 * @return int The type.
	 */
	public function type(): int {
		return $this->m_type;
	}

	/**
	 * Set the type of the text edit widget.
	 *
	 * The type must be one of the edit type class constants. Any other value is considered an error.
	 *
	 * @param $type int The widget type.
	 *
	 * @return bool _true_ if the type was set, _false_ otherwise.
	 */
	public function setType(int $type) {
		if($type === self::SingleLine || $type === self::Password || $type === self::Email || $type === self::Url || $type === self::Search || $type === self::MultiLine) {
			$this->m_type = $type;
			return true;
		}

		AppLog::error("invalid type", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the widget's text.
	 *
	 * @return string The text for the widget, or _null_ if no text is set.
	 */
	public function text(): ?string {
		return $this->attribute("value");
	}

	/**
	 * Set the widget's text.
	 *
	 * The text can be _null_ to unset the existing text.
	 *
	 * @param $text string The text for the widget.
	 */
	public function setText(?string $text): void {
		$this->setAttribute("value", $text);
	}

	/**
	 * Fetch the widget's autocomplete attribute setting.
	 *
	 * @return string|null The autocomplete attribute for the widget, or _null_ if no autocomplete attribute is set.
	 */
	public function autocomplete(): ?string {
		return $this->attribute("autocomplete");
	}

	/**
	 * Set the widget's autocomplete attribute.
	 *
	 * Valid values for the autocomplete attribute can be found at
	 * https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete
	 *
	 * If the provided value is a _bool_, it is converted to "on" if _true_ or "off" if _false_.
	 *
	 * This method **does not** validate the string you provide. It is your responsibility to ensure that the value is
	 * valid. If you provide an invalid value, the page in which the widget is used is likely to fail validation.
	 *
	 * @param $autocomplete bool|string|null The autocomplete attribute for the widget.
	 */
	public function setAutocomplete($autocomplete): void {
		if(is_bool($autocomplete)) {
			$autocomplete = ($autocomplete ? "on" : "off");
		}

		assert(is_string($autocomplete) || is_null($autocomplete), "invalid argument to setAutocomplete() - must be bool, string or null");
		$this->setAttribute("autocomplete", $autocomplete);
	}

	/**
	 * Generate the HTML for a single-line text edit widget.
	 *
	 * This is an internal helper function that is used by html() to generate the content when the widget's type is one
	 * of the single-line types:
	 * - _SingleLine_
	 * - _Email_
	 * - _Url_
	 * - _Search_
	 * - _Password_
	 *
	 * The _$actualType_ argument is not validated. As an internal private method, it relies on the call site to
	 * ensure a valid string is provided.
	 *
	 * @param $actualType string _optional_ The actual type.
	 *
	 * @return string The HTML.
	 */
	private function emitSingleLineType(string $actualType = "text"): string {
		return "<input type=\"$actualType\"" . $this->emitAttributes() . " />";
	}

	/**
	 * Generate the HTML for a multi-line text edit widget.
	 *
	 * This is an internal helper function that is used by html() to generate the content when the widget's type is
	 * _MultiLine_.
	 *
	 * @return string The HTML.
	 */
	private function emitMultiLineType(): string {
		$text = $this->text();
		$this->setText(null);
		$ret = "<textarea" . $this->emitAttributes() . ">" . (empty($text) ? "" : html($text)) . "</textarea>";
		$this->setText($text);
		return $ret;
	}

	/**
	 * Generate the HTML for the widget.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		switch($this->m_type) {
			case self::SingleLine:
				return $this->emitSingleLineType();

			case self::Password:
				return $this->emitSingleLineType("password");

			case self::Email:
				return $this->emitSingleLineType("email");

			case self::Url:
				return $this->emitSingleLineType("url");

			case self::Search:
				return $this->emitSingleLineType("search");

			case self::MultiLine:
				return $this->emitMultiLineType();
		}

		/* this should never happen */
		return "";
	}
}
