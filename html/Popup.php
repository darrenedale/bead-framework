<?php

/**
 * Defines the Popup class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/Application.php
 * - classes/equit/Page.php
 * - classes/equit/html/Division.php
 *
 * ### Changes
 * - (2017-05) Finished documentation.
 * - (2014-05-21) First version of this file.
 *
 * @file Popup.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit\Html;

use Equit\AppLog;

/**
 * A section that pops up when the user manipulates an anchor.
 *
 * The section has an anchor as the first child, and any child elements added to the section are output after that. All
 * _PopupSection_ elements have the class name _popup-section_. The anchor is contained in its own _div_ with the class
 * _popup-anchor_, and the child elements of the popup section are all contained inside a _div_ with the class
 * _popup-content_.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This module does not provide any API functions.
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
 * @class PopupSection
 * @author Darren Edale
 * @package libequit
 */
class Popup extends Division {
	/** @var int Trigger the popup on click. */
	public const ClickTrigger = 0x01;

	/** @var int Trigger the popup on hover. */
	public const HoverTrigger = 0x02;

	/** @var int Trigger the popup on all available triggers. */
	public const AllTriggers = 0xff;

	/** @var int The default triggers for newly-created popup sections. */
	public const DefaultTriggers = self::ClickTrigger;

	/** @var string The CSS class name for popup sections. */
	private const HtmlClassName = "equit-popup";

	/** @var string|PageElement|null The anchor for the popup section. */
	private $m_anchor = null;

	/** @var int The set of triggers for the popup action. */
	private $m_triggers = self::DefaultTriggers;

	/**
	 * Create a new PopupSection.
	 *
	 * By default a popup section without an ID is created.
	 *
	 * @param $id string _optional_ The ID for the popup section.
	 */
	public function __construct($id = null) {
		if(empty($id)) {
			$id = self::generateUid();
		}

		parent::__construct($id);
	}

	/**
	 * Fetch the URL of the runtime support javascript.
	 *
	 * @return array The support javascript URLs.
	 */
	public static function runtimeScriptUrls(): array {
		return ["js/Popup.js"];
//		return ["js/popup.js"];
	}

	/**
	 * Set the anchor for the PopupSection.
	 *
	 * @param $anchor string|PageElement The anchor.
	 *
	 * @return bool _true_ if the anchor was set, _false_ otherwise.
	 */
	public function setAnchor($anchor): bool {
		if(is_null($anchor) || is_string($anchor) || ($anchor instanceof PageElement)) {
			$this->m_anchor = $anchor;
			return true;
		}

		AppLog::error("invalid anchor", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the anchor for the PopupSection.
	 *
	 * @return string|PageElement|null The anchor, or _null_ if no anchor is set.
	 */
	public function anchor() {
		return $this->m_anchor;
	}

	/**
	 * Set the triggers for the PopupSection.
	 *
	 * The set of triggers must be a bitwise _or_ of the trigger
	 * masks for the triggers you wish to be in use.
	 *
	 * @param $triggers int The set of triggers.
	 *
	 * @return void.
	 */
	public function setTriggers(int $triggers): void {
		$this->m_triggers = $triggers & self::AllTriggers;
	}

	/**
	 * Fetch the triggers for the PopupSection.
	 *
	 * @return int The set of triggers.
	 */
	public function triggers(): int {
		return $this->m_triggers;
	}

	/** Set whether or not clicking on the anchor triggers the popup
	 * section.
	 *
	 * @param $trigger bool Whether or not clicking should act as a
	 * trigger.
	 *
	 * @return void
	 */
	public function setClickTriggers(bool $trigger): void {
		if(!$trigger) {
			$this->m_triggers &= ~self::ClickTrigger;
		}
		else {
			$this->m_triggers |= self::ClickTrigger;
		}
	}

	/**
	 * Set whether or not hovering over the anchor triggers the popup section.
	 *
	 * @param $trigger bool Whether or not hovering should act as a
	 * trigger.
	 *
	 * @return void
	 */
	public function setHoverTriggers(bool $trigger): void {
		if(!$trigger) {
			$this->m_triggers &= ~self::HoverTrigger;
		}
		else {
			$this->m_triggers |= self::HoverTrigger;
		}
	}

	/**
	 * Check whether clicking on the anchor will trigger the popup section.
	 *
	 * @return bool _true_ if the click trigger is set, _false_ otherwise.
	 */
	public function clickTriggers(): bool {
		return (bool)($this->m_triggers & self::ClickTrigger);
	}

	/**
	 * Check whether hovering over the anchor will trigger the popup
	 * section.
	 *
	 * @return bool _true_ if the hover trigger is set, _false_ otherwise.
	 */
	public function hoverTriggers(): bool {
		return (bool)($this->m_triggers & self::HoverTrigger);
	}

	/**
	 * Generate the HTML for the section.
	 *
	 * The popup section is output as a single `&lt;div&gt;` element with the
	 * class `popup-section`. The element will also have whatever classes and
	 * ID are set for it by the code using the element. The direct children of
	 * this element will be two `&lt;div&gt;` elements, one for the anchor and
	 * the other for the section's children. These will have the classes
	 * `popup-anchor` and `popup-content` respectively.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		$anchor = $this->anchor();
		$id     = $this->id();

		if(empty($id)) {
			$this->setId(self::generateUid());
		}

		if(is_string($anchor)) {
			$anchor = html($anchor);
		}
		else if($anchor instanceof PageElement) {
			$anchor = $anchor->html();
		}

		$classNames = $this->classNames();
		$hasClass   = is_array($classNames) && in_array(self::HtmlClassName, $this->classNames());

		if(!$hasClass) {
			$this->addClassName(self::HtmlClassName);
		}

		$triggersData = $this->data("triggers");
		$triggers = [];

		if($this->triggers() & self::ClickTrigger) {
			$triggers[] = "click";
		}

		if($this->triggers() & self::HoverTrigger) {
			$triggers[] = "hover";
		}

		if(empty($triggers)) {
			$triggers = ["click"];
		}

		$this->setData("triggers", implode("|", $triggers));

		$ret = $this->emitDivisionStart() . "<div class=\"popup-anchor\">$anchor</div><div class=\"popup-content\">";

		/** @var \Equit\Html\PageElement $child */
		foreach($this->childElements() as $child) {
			$ret .= $child->html();
		}

		$ret .= "</div><!-- popup-content -->" . $this->emitDivisionEnd();

		if(!$hasClass) {
			$this->removeClassName(self::HtmlClassName);
		}

		$this->setData("triggers", $triggersData);

		if(empty($id)) {
			$this->setId($id);
		}

		return $ret;
	}
}

