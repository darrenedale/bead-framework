<?php

namespace Equit\Html;

/**
 * Basic implementation of the ContainerPageElement interface to be used in PageElement subclasses that implement the
 * interface.
 *
 * It also provides a convenience protected method to emit the HTML for the child elements. This is not part of the
 * ContainerPageElement interface.
 *
 * @package Equit\Html
 */
trait HasChildElements
{
	/**
	 * Add a child element.
	 *
	 * Child elements are added after the last existing child element; or, put another way, child elements are always
	 * added in series. There is, as yet, no facility to add a child at any other point in the element's child list.
	 *
	 * The base method always succeeds; the return value is for subclasses that reimplement this method to place
	 * restrictions on the types of children that may be added.
	 *
	 * @param $child PageElement The element to add.
	 *
	 * @return bool `true` if the child element was added, `false` if not.
	 */
	public function addChildElement(PageElement $child): bool {
		$this->m_children[] = $child;
		return true;
	}

	/**
	 * Fetch the element's children.
	 *
	 * The children are returned in the order in which they will appear in the element when the HTML is generated.
	 *
	 * @return array[PageElement] The element's children.
	 */
	public function childElements(): array {
		return $this->m_children;
	}

	/** Clear the child elements.
	 *
	 * All child elements are removed.
	 */
	public function clearChildElements(): void {
		$this->m_children = [];
	}

	/**
	 * Generate the HTML for the element's child elements.
	 *
	 * This is a helper method for use when generating the HTML.
	 *
	 * @return string The HTML for the child elements.
	 */
	protected function emitChildElements(): string {
		$ret = "";

		foreach($this->m_children as $child) {
			$ret .= $child->html();
		}

		return $ret;
	}

	/** @var array<PageElement> The child elements for the parent element. */
	private array $m_children = [];
}
