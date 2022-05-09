<?php

/**
 * Defines the StaticValueWidget class.
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
 * @file StaticValueWidget.php
 * @author Darren Edale
 * @version 1.2.0
 * @date Jan 2018
 * @package libequit
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\HiddenValueWidget;

/**
 * A widget for inclusion in forms that contains a fixed value with a display text.
 *
 * A static value widget is like a label combined with a hidden value widget. It is like a label in that it contains
 * static content that the user is not able to alter, and it's like a hidden value widget in that it contains a
 * value that the user is unable to modify that is submitted with the form data.
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
 * @class StaticValueWidget
 * @author Darren Edale
 * @package libequit
 */
class StaticValueWidget extends HiddenValueWidget {
	/** @var int Content type enumerator representing plain text content. */
	const PlainTextContent = 0;

	/** @var int Content type enumerator representing HTML content. */
	const HtmlContent      = 1;

	/** @var string The display content for the widget.  */
	private $m_content     = "";

	/** @var int The type of content. */
	private $m_contentType = self::PlainTextContent;

	/**
	 * Create a new static value widget.
	 *
	 * @param $id string _optional_ The ID for the static value widget.
	 *
	 * By default a widget with no ID is created.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Fetch the display content for the widget.
	 *
	 * @return string The content.
	 */
	public function content(): string {
		return $this->m_content;
	}

	/**
	 * Set the content of the static value widget.
	 *
	 * If the _$type_ parameter is provided it must be either _PlainTextContent_ or _HtmlContent_. If it is not
	 * provided, it defaults to _PlainTextContent_.
	 *
	 * ### Note
	 * Not providing the _$type_ parameter does not retain the type of the existing content, it resets the content
	 * type to _PlainTextContent_.
	 *
	 * @param $content string The new content.
	 * @param $type int _optional_ The content type for the new content.
	 *
	 * @return bool _true_ if the content and type were set, _false_ otherwise.
	 */
	public function setContent(string $content, int $type = self::PlainTextContent): bool {
		if(self::PlainTextContent !== $type && self::HtmlContent != $type) {
			AppLog::error("invalid content type $type", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_content     = $content;
		$this->m_contentType = $type;
		return true;
	}

	/**
	 * Fetch the type of the current content of the static value widget.
	 *
	 * @return int PlainTextContent or HtmlContent.
	 */
	public function contentType(): int {
		return $this->m_contentType;
	}

	/**
	 * Set the type of the current content of the static value widget.
	 *
	 * The type must be either PlainTextContent or HtmlContent. Anything else is
	 * considered an error. Changing the content type has no effect on the
	 * acutal content, it just changes how it is handled when the widget is
	 * generated. Plain text content will be escaped to ensure that it is output
	 * as valid HTML whereas HTML content will be assumed to be valid already
	 * and will be output verbatim.
	 *
	 * ### Warning
	 * Don't set the type to _HtmlContent_ unless you are sure that the content is valid UTF-8 encoded HTML. Setting
	 * the content type to HTML without ensuring this could result in an invalid or corrupt page.
	 *
	 * @param $type int The new type.
	 *
	 * @return bool _true_ if the content type was set, _false_ otherwise.
	 */
	public function setContentType($type) {
		if(self::PlainTextContent !== $type && self::HtmlContent != $type) {
			AppLog::error('invalid content type', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_contentType = $type;
		return true;
	}

	/**
	 * Generate the HTML for the widget.
	 *
	 * The static value widget is output as a single _<div>_ element. The element will have the class
	 * *static_widget* and will contain a hidden value widget (see _HiddenValueWidget::html()_ for details) and the
	 * content specified.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return "<div class=\"static_widget\">" . parent::html() . (self::HtmlContent == $this->m_contentType ? $this->m_content : html($this->m_content)) . "</div>";
	}
}
