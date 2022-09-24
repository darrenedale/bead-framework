<?php

namespace Equit\Html;

/**
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes. It will be
 * removed before the version 2.0 release.
 */
class ListItem extends Element implements ContainerElement {
	use HasChildElements;

	/**
	 * Initialise a new ListItem.
	 *
	 * @param string|null $id The ID of the list item.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Helper method to generate the opening _li_ tag.
	 *
	 * @return string The item start tag.
	 */
	protected function emitItemStart(): string {
		return "<li{$this->emitAttributes()}>";
	}

	/**
	 * Helper method to generate the closing  _li_ tag.
	 *
	 * @return string The item end tag.
	 */
	protected function emitItemEnd(): string {
		return "</li>";
	}

	/**
	 * Generate the list item's HTML.
	 *
	 * @return string The HTML for the list item.
	 */
	public function html(): string {
		return "{$this->emitItemStart()}{$this->emitChildElements()}{$this->emitItemEnd()}";
	}
}