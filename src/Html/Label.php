<?php

/**
 * Defines the Label class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\PageElement.php
 * - classes/equit/HtmlTooltip.php
 *
 * ### Changes
 * - (2017-05) Updated documentation.
 * - (2013-12-10) First version of this file.
 *
 * @file Label.php
 * @author Darren Edale
 * @version 0.9.2
 * @package libequit
 * @version 0.9.2 */

namespace Equit\Html;

use Equit\AppLog;

/**
 * A static label element to include in a page.
 *
 * Labels are useful in forms as labels for other widgets. They don't submit any data with the form. While this is their
 * primary intended use, it is valid to use _Label_ objects elsewhere in the page, although it is likely that other
 * classes are a better fit for such usage (e.g. _HtmlSection_).
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
 * @class Label
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
class Label extends Element {
	use HasTooltip;

	/** @var int Content type constant for plain text content. */
	const PlainTextContent = 1;

	/** @var int Content type constant for HTML content. */
	const HtmlContent = 2;

	/** @var array The HTML attributes supported by Label objects. */
	private static $s_labelAttributeNames = ["title"];

	/** @var string The label content. */
	private $m_content = "";

	/** @var int The type of content in the label. */
	private $m_contentType = self::PlainTextContent;

	/**
	 * Initialise a new Label.
	 *
	 * Label widgets can have either plain text or HTML content. By default, any content provided to the constructor is
	 * treated as plain text.
	 *
	 * If no ID is set the label will be generated without an _id_ attribute.
	 *
	 * @param $content string _optional_ The content for the label.
	 * @param $id string _optional_ The ID for the label.
	 */
	public function __construct(string $content = "", ?string $id = null) {
		parent::__construct($id);
		$this->setContent($content);
	}

	/**
	 * Fetch the type of the current content of the label.
	 *
	 * @return int PlainTextContent or HtmlContent.
	 */
	public function contentType(): int {
		return $this->m_contentType;
	}

	/** Set the type of the current content of the label.
	 *
	 * The type must be either _PlainTextContent_ or _HtmlContent_. Anything else is considered an error. Changing the
	 * content type has no effect on the actual content, it just changes how it is handled when the label is generated.
	 * Plain text content will be escaped to ensure that it is output as valid HTML whereas HTML content will be assumed
	 * to be valid already and will be output verbatim.
	 *
	 * ### Warning
	 * Don't set the type to _HtmlContent_ unless you are certain that the content provided is valid UTF-8 encoded HTML.
	 * Setting the content type to HTML without ensuring this could result in an invalid or corrupt page.
	 *
	 * @param $type int The new type.
	 *
	 * @return bool _true_ if the content type was set, _false_ if an invalid content type value was provided.
	 */
	public function setContentType(int $type): bool {
		if($type === self::PlainTextContent || $type == self::HtmlContent) {
			$this->m_contentType = $type;
			return true;
		}

		AppLog::error("invalid label content type $type", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the label's content.
	 *
	 * @return string The content. This will be an empty string if no content is set.
	 */
	public function content(): string {
		return $this->m_content;
	}

	/**
	 * Set the content of the label.
	 *
	 * The _$type_ argument is optional, but if provided it must be either _PlainTextContent_ or _HtmlContent_. Any
	 * other value is considered an error. If it is not provided, it defaults to _PlainTextContent_.
	 *
	 * ### Note
	 * Not providing an argument for the _$type_ parameter does not retain the type of the existing content, it resets
	 * the content type to _PlainTextContent_.
	 *
	 * @param $content string The new content.
	 * @param $type int The content type for the new content.
	 *
	 * @return bool _true_ if the content and type were set, _false_ otherwise.
	 */
	public function setContent(string $content, int $type = self::PlainTextContent): bool {
		if(!$this->setContentType($type)) {
			return false;
		}

		$this->m_content = $content;
		return true;
	}

	/**
	 * Generate the HTML for the label.
	 *
	 * The label is output as a single &lt;div&gt; element. The element will have whatever classes are set for it by the
	 * code using the label, plus the class *label_widget*.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		$classNames = $this->classNames();
		$this->addClassName("label");
		$ret = "<div" . $this->emitAttributes() . ">" . (self::HtmlContent === $this->m_contentType ? $this->m_content : html($this->m_content)) . "</div>";
		$this->setClassNames($classNames);

		return $ret;
	}
}
