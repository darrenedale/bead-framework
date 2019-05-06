<?php

namespace Equit\Html;

/**
 * Page elements that act as containers for other elements should implement this interface.
 *
 * @package Equit\Html
 */
interface ContainerPageElement {
	/**
	 * Add a child element to the container.
	 *
	 * @param $child PageElement The child element to add.
	 *
	 * @return bool `true` if the child element was added, `false` otherwise.
	 */
	public function addChildElement(PageElement $child): bool;

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