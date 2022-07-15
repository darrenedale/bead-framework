<?php

namespace Equit\Html;

/**
 * A `HR` element for use in HTML pages.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class HorizontalRule extends Element {
	/**
	 * Generate the HTML for the element.
	 *
	 * @return string The HTML for the HR element.
	 */
	public function html(): string {
		return "<hr{$this->emitAttributes()} />";
	}
}
