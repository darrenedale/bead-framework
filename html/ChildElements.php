<?php

namespace Equit\Html;

trait ChildElements {

	/**
	 * Add a child element.
	 *
	 * Child elements are added after the last existing child element; or, put another way, child elements are always
	 * added in series. There is, as yet, no facility to add a child at any other point in the element's child list.
	 *
	 * @param $child PageElement The element to add.
	 */
	public function addChild(PageElement $child): void {
		$this->m_children[] = $child;
	}

	/**
	 * Fetch the element's children.
	 *
	 * The children are returned in the order in which they will appear in the element when the HTML is generated.
	 *
	 * @return array[PageElement] The element's children.
	 */
	public function children(): array {
		return $this->m_children;
	}

	/** Clear the child elements.
	 *
	 * All child elements are removed.
	 *
	 * @todo refactor: rename clearChildren()
	 */
	public function clear(): void {
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

	/** @var array[PageElement] The child elements for the parent element. */
	private $m_children = [];
}
