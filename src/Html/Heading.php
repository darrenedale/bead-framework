<?php

/**
 * Defines the LibEquit\HtmlHeading class.
 *
 * ### Dependencies
 * - classes/equit/PageElement.php
 *
 * ### Changes
 * - (2019-03) First version of this file.
 *
 * @file HtmlHeading.php
 * @author Darren Edale
 * @version 0.9.1
 * @package libequit
 * @version 0.9.1 */

namespace Equit\Html;

/**
 * A heading element for use in HTML pages.
 *
 * This generates <h1> to <h6> heading elements. The content of the heading is fully configurable by providing
 * PageElement objects, or can simply be used with plain text, which will be HTML-escaped before output.
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
 * @class LibEquit\HtmlLiteral
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
class Heading extends PageElement {
	/**
	 * Initialise a new HTML Heading.
	 *
	 * If an invalid heading level is provided
	 *
	 * @param $content string|PageElement The content for the heading.
	 * @param int $level The level, 1-6.
	 * @param string|null $id
	 */
	public function __construct($content = "", int $level = 1, ?string $id = null) {
		parent::__construct($id);
		$this->setContent($content);
		$this->setLevel($level);
	}

	/**
	 * Set the heading level.
	 *
	 * The heading must be between 1 and 6 inclusive. If it is invalid, the heading level will remain unchanged.
	 *
	 * @param int $level
	 *
	 * @return bool `true` if the heading level was set, `false` otherwise.
	 */
	public final function setLevel(int $level): bool {
		if(1 > $level || 6 < $level) {
			return false;
		}

		$this->m_level = $level;
		return true;
	}

	/**
	 * Fetch the heading level.
	 *
	 * The heading level is guaraneed to be be between 1 and 6 inclusive.
	 *
	 * @return int The heading level.
	 */
	public final function level(): int {
		return $this->m_level;
	}

	/**
	 * Fetch the heading content.
	 *
	 * @return string|PageElement The heading content.
	 */
	public function content() {
		return $this->m_content;
	}

	/**
	 * Set the heading content.
	 *
	 * The content must be either a plain string or a PageElement. An assertion failure results if it is neither.
	 *
	 * Set the content to an empty string if you want an empty heading.
	 *
	 * @param $content string|PageElement The content for the heading.
	 */
	public function setContent($content) {
		if(!is_string($content) && !($content instanceof PageElement)) {
			trigger_error(tr("Internal error generating page content (%1)", __FILE__, __LINE__, "ERR_INVALID_HTMLHEADING_CONTENT"), E_USER_ERROR);
		}

		$this->m_content = $content;
	}

	/**
	 * Generate the HTML for the element.
	 *
	 * @return string The HTML for the HR element.
	 */
	public function html(): string {
		$content = $this->content();

		if(is_string($content)) {
			$content = html($content);
		}
		else {
			$content = $content->html();
		}

		return "<h{$this->m_level} {$this->emitAttributes()}>{$content}</h{$this->m_level}>";
	}

	/**
	 * @var int The heading level (1 - 6)
	 */
	private $m_level = 1;

	/**
	 * @var string|\Equit\Html\PageElement The heading content.
	 */
	private $m_content = "";
}
