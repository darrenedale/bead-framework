<?php

namespace Equit\Html;

/**
 * A page element intended to act as a container for other page elements.
 *
 * Objects of this class are useful to act as containers for other elements. The Page class uses Division
 * elements for its core sections. Child elements are added using addChild() and can be retrieved using children().
 * The division can be cleared of all its child elements with the clear() method. There is as yet no facility to remove
 * individual children.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class Division extends Element implements ContainerElement {
	use HasTooltip;
	use HasChildElements;

	/** Create a new PageDivision object.
	 *
	 * The ID parameter is optional. By default, a division with no ID is created.
	 *
	 * @param $id string|null The ID for the division.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/** Generate the opening HTML for the division.
	 *
	 * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
	 * don't need to replicate the common HTML for the start of the division element and need only implement their
	 * custom content.
	 *
	 * The start is generated as a _div_ element with the ID and classes specified by the creator, if any have been
	 * provided.
	 *
	 * @return string The opening HTML.
	 */
	protected function emitDivisionStart(): string {
		return "<div{$this->emitAttributes()}>";
	}

	/** Generate the closing HTML for the division.
	 *
	 * This is a helper method for use when generating the HTML. It could be useful for subclasses to call so that they
	 * don't need to replicate the common HTML for the end of the division element and need only implement their custom
	 * content.
	 *
	 * The end is generated as a closing &lt;/div&gt; tag.
	 *
	 * @return string The closing HTML.
	 */
	protected function emitDivisionEnd(): string {
		return "</div>";
	}

	/**
	 * Generate the HTML for the division.
	 *
	 * The division is output as a single _div_ element. The element will have whatever classes and ID are set for it by
	 * the code using the division.
	 *
	 * This method generates UTF-8 encoded HTML 5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return $this->emitDivisionStart() . $this->emitChildElements() . $this->emitDivisionEnd();
	}
}
