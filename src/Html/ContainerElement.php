<?php

namespace Equit\Html;

/**
 * Page elements that act as containers for other elements should implement this interface.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
interface ContainerElement {
	/**
	 * Add a child element to the container.
	 *
	 * @param $child Element The child element to add.
	 *
	 * @return bool `true` if the child element was added, `false` otherwise.
	 */
	public function addChildElement(Element $child): bool;

	/**
	 * Fetch all the child elements.
	 *
	 * @return array[PageElement] The child elements.
	 */
	public function childElements(): array;

	/**
	 * Clear all child elements from the container.
	 */
	public function clearChildElements(): void;
}