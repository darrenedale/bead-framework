<?php

namespace Equit\Html;

/**
 * A page element intended to act as a major section of a page.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes. It will be
 * removed before the version 2.0 release.
 */
class Section extends Element implements ContainerElement {
	use HasTooltip;
	use HasChildElements;

	/** Create a new Section object.
	 *
	 * The ID parameter is optional. By default, a section with no ID is created.
	 *
	 * @param $id string _optional_ The ID for the section.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Generate the opening HTML for the section.
	 *
	 * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
	 * don't need to replicate the common HTML for the start of the section element and need only implement their
	 * custom content.
	 *
	 * The start is generated as a _section_ element with the ID and classes specified by the creator, if any have been
	 * provided.
	 *
	 * @return string The opening HTML.
	 */
	protected function emitSectionStart(): string {
		return "<section{$this->emitAttributes()}>";
	}

	/**
	 * Generate the closing HTML for the section.
	 *
	 * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
	 * don't need to replicate the common HTML for the end of the section element and need only implement their custom
	 * content.
	 *
	 * The end is generated as a closing _section_ tag.
	 *
	 * @return string The closing HTML.
	 */
	protected function emitSectionEnd(): string {
		return "</section>";
	}

	/**
	 * Generate the HTML for the section.
	 *
	 * The section is output as a single _section_ element. The element will have whatever classes and ID are set for it
	 * by the code using the section.
	 *
	 * This method generates UTF-8 encoded HTML 5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return $this->emitSectionStart() . $this->emitChildElements() . $this->emitSectionEnd();
	}
}
