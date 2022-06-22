<?php

/**
 * Defines the LibEquit\HtmlLiteral class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/PageElement.php
 *
 * ### Changes
 * - (2018-09) Uses string type hints.
 * - (2017-05) Updated documentation. Removed unused dependencies.
 * - (2013-12-10) First version of this file.
 *
 * @file HtmlLiteral.php
 * @author Darren Edale
 * @version 0.9.2
 * @package libequit
 * @version 0.9.2 */

namespace Equit\Html;

/**
 * Allows arbitrary HTML to be added to the page.
 *
 * This class is just a thin LibEquit\PageElement wrapper around a string containing pre-validated HTML.
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
class HtmlLiteral extends Element {
	/** @var string The HTML. */
	private $m_html = "";

	/**
	 * Create a new LibEquit\HtmlLiteral.
	 *
	 * By default, an empty HTML literal object is created.
	 *
	 * @param $html string _optional_ The HTML to wrap in the object.
	 */
	public function __construct(string $html = "") {
		parent::__construct();
		$this->setHtml($html);
	}

	/**
	 * Set the HTML to wrap in the LibEquit\PageElement.
	 *
	 * The HTML is used verbatim with no modifications and is not validated. It must therefore be provided as valid
	 * HTML. To unset the existing HTML, provide an empty string.
	 *
	 * @param $html string The HTML to use.
	 *
	 * @return void.
	 */
	public function setHtml(string $html): void {
		$this->m_html = $html;
	}

	/**
	 * Fetch the HTML.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return $this->m_html;
	}
}
