<?php

/**
 * Defines the PushButton class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\PageElement.php
 *
 * ### Changes
 * - (2017-05) Updated documentation.
 * - (2013-12-10) First version of this file.
 *
 * @file PushButton.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\Tooltip;
use Equit\Html\PageElement;

/**
 * A push button for inclusion in forms.
 *
 * Push buttons generally are used to perform actions at runtime in web forms
 * but also submit data with the form. Buttons have a name and value like other
 * form elements, and have a text which is displayed on the face of the button.
 *
 * There are flags available to control the bahaviour of the button. Presently
 * just one flag is available - Submit - which makes the push button a submit
 * button for the form in which it is embedded.
 *
 * As yet there are no facilities to enable push buttons to have runtime code
 * attached. This is likely to follow in the near future.
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
 * @noconnections
 * ### Connections
 * This module does not connect to any events.
 *
 * @nosettings
 * ### Settings
 * This module does not read any settings.
 *
 * @nosession
 * ### Session Data
 * This module does not create a session context.
 *
 * @class PushButton
 * @author Darren Edale
 * @package libequit
 */
class PushButton extends PageElement {
	use Tooltip;

	/** Flag to mark a push button as a form submission button. */
	const Submit = 0x01;

	/** Default push button flags. */
	const DefaultFlags = 0x00;

	/** The attributes available for push buttons. */
	private static $s_pushButtonAttributeNames = ['value', 'name', 'title'];

	/** The pushbutton's flags. */
	private $m_flags = self::DefaultFlags;

	/**
	 * Create a new push button.
	 *
	 * @param $text string _optional_ The text to display on the button.
	 * @param $id string _optional_ The unique ID for the button.
	 * @param $flags int _optional_ Flags controlling the behaviour of the button.
	 *
	 * By default, an empty button with no ID and no flags is created.
	 */
	public function __construct($text = '', $id = null, $flags = self::DefaultFlags) {
		parent::__construct($id);

		foreach(self::$s_pushButtonAttributeNames as $name) {
			$this->setAttribute($name, null);
		}

		$this->setText($text);
		$this->setFlags($flags);
	}

	/**
	 * Fetch the flags for the button.
	 *
	 * The flags control various aspects of teh button's behaviour. Currently,
	 * just one flag is available - Submit - which makes the button a form
	 * submission button.
	 *
	 * @return int The flags.
	 */
	public function flags() {
		return $this->m_flags;
	}

	/**
	 * Set the flags for the button.
	 *
	 * @param $flags `int` The flags.
	 *
	 * The flags control various aspects of teh button's behaviour. Currently,
	 * just one flag is available - `Submit` - which makes the button a form
	 * submission button. The flags are a bitmask of flag constants from this
	 * class. Anything else found in the bitmask provided is simply ignored.
	 *
	 * @return bool `true` if the flags were set, `false` otherwise.
	 */
	public function setFlags($flags) {
		if(is_int($flags)) {
			$this->m_flags = $flags;
			return true;
		}

		AppLog::error('invalid flags', __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the name of the push button.
	 *
	 * The name is used as the key for the value submitted by this element with
	 * the form data.
	 *
	 * @return string The name, or `null` if no name has been set.
	 */
	public function name() {
		return $this->attribute('name');
	}

	/**
	 * Set the name of the push button.
	 *
	 * @param $name `string` The name.
	 *
	 * The name is used as the key for the value submitted by this element with
	 * the form data. It can be set to `null` to unset the current name.
	 *
	 * @return bool `true` if the name was set, `false` otherwise.
	 */
	public function setName($name) {
		return $this->setAttribute('name', $name);
	}

	/**
	 * Fetch the text displayed on the push button.
	 *
	 * @return string The text, or `null` if none is set.
	 */
	public function text() {
		return $this->attribute('value');
	}

	/**
	 * Set the text displayed on the push button.
	 *
	 * @param $text `string` The text.
	 *
	 * The text can be set to `null` to unsed the existing text.
	 *
	 * @return bool `true` if the text was set, `false` otherwise.
	 */
	public function setText($text) {
		return $this->setAttribute('value', $text);
	}

	/**
	 * Set the `onclick` attribute for the push button.
	 *
	 * @param $attrValue `string` The value for the `onclick` attribute.
	 *
	 * @return bool `true` if the attribute was set, `false` otherwise.
	 */
	public function setOnClick($attrValue) {
		return $this->setAttribute('onclick', $attrValue);
	}

	/**
	 * Fetch the `onclick` attribute for the push button.
	 *
	 * @return string The value for the `onclick` attribute, or `null` if
	 * the attribute is not set.
	 */
	public function onClick() {
		return $this->attribute('onclick');
	}

	/**
	 * Generate the HTML for the push button.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return '<input type="' . ($this->m_flags & self::Submit ? 'submit' : 'button') . '"' . $this->emitAttributes() . ' />';
	}
}
