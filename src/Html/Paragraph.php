<?php

/**
 * Defines the Paragraph page element class.
 *
 * ### Dependencies
 * - Equit\Html\PageElement.php
 * - Equit\Html\Tooltip.php
 *
 * ### Changes
 * - (2019-03) First version of this file.
 *
 * @file Paragraph.php
 * @author Darren Edale
 * @version 1.2.0
 * @package libequit
 * @date Jan 2018December 2013
 */

namespace Equit\Html;

/**
 * A page element intended to act as a container for other page elements.
 *
 * Objects of this class are useful to act as containers for other elements.
 * The LibEquit\Page class uses PageDivision elements for its core sections. Child
 * elements are added using addChild() and can be retrieved using children().
 * The section can be cleared of all its child elements with the clear()
 * method. There is as yet no facility to remove children.
 *
 * @class Paragraph
 * @author Darren Edale
 * @package Equit\Html
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class Paragraph extends PageElement implements ContainerPageElement {
	use HasTooltip;
	use HasChildElements;

	/** Initialiwse a new Paragraph object.
	 *
	 * The ID parameter is optional. By default, a paragraph with no ID is created.
	 *
	 * @param $content string|PageElement|null The content for the paragraph.
	 * @param $id string _optional_ The ID for the section.
	 */
	public function __construct($content = null, ?string $id = null) {
		assert(is_null($content) || is_string($content) || $content instanceof PageElement, "invalid content provided for Paragraph object");
		parent::__construct($id);

		if(isset($content)) {
			if(is_string($content)) {
				$content = new HtmlLiteral(html($content));
			}

			$this->addChildElement($content);
		}
	}

	/** Generate the opening HTML for the paragraph.
	 *
	 * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
	 * don't need to replicate the common HTML for the start of the paragraph element and need only implement their
	 * custom content.
	 *
	 * The start is generated as a _p_ element with the ID and classes specified by the creator, if any have been
	 * provided.
	 *
	 * @return string The opening HTML.
	 */
	protected function emitParagraphStart(): string {
		return "<p{$this->emitAttributes()}>";
	}

	/**
	 * Generate the closing HTML for the paragraph.
	 *
	 * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
	 * don't need to replicate the common HTML for the end of the paragraph element and need only implement their custom
	 * content.
	 *
	 * The end is generated as a closing &lt;/p&gt; tag.
	 *
	 * @return string The closing HTML.
	 */
	protected function emitParagraphEnd(): string {
		return "</p>";
	}

	/**
	 * Generate the HTML for the paragraph.
	 *
	 * The section is output as a single &lt;p&gt; element. The element will have whatever classes and ID are set for it
	 * by the code using the paragraph.
	 *
	 * This method generates UTF-8 encoded HTML 5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return $this->emitParagraphStart() . $this->emitChildElements() . $this->emitParagraphEnd();
	}
}
