<?php

namespace Equit\Html;

/** An interface for page element layouts.
 *
 * This class defines the interface that must be implemented to provide a layout for a web page.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes. It will be
 * removed before the version 2.0 release.
 */
abstract class Layout extends Element{
	/**
	 * Create a new layout.
	 *
	 * By default, a layout with no ID is created.
	 *
	 * @param $id string _optional_ The ID for the layout.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Add an element to the layout.
	 *
	 * @param $element Element is the element to add.
	 *
	 * The element is added to the layout. Further parameters can be defined that allow for customisation of exactly how
	 * the element is added (for example, an order number or coordinate).
	 *
	 * @return bool `true` if the element was added, `false` otherwise.
	 */
	public abstract function addElement(Element $element): bool;

	/**
	 * Fetch the child elements in the layout.
	 *
	 * This method should return all the elements (not layouts) that are
	 * included in the layout. They must be provided as an array. The order of
	 * the elements is not dictated by the definition of this method, but it
	 * should be an order that is logical and consistent with the general
	 * layout paradigm implemented by the class. The array must not be multi-
	 * dimensional.
	 *
	 * @return array[LibEquit\PageElement] the children.
	 */
	public abstract function elements(): array;

	/**
	 * Fetch the number of form elements the layout has.
	 *
	 * The number of children is the number of form elements included in the
	 * layout.
	 *
	 * @return int The number of children.
	 */
	public abstract function elementCount(): int;

	/**
	 * Clear all content from the layout.
	 */
	public abstract function clear(): void;

	/**
	 * Generates the layout markup appropriate for the document type.
	 *
	 * Each child element and layout must be asked to generate its own markup
	 * within the structure defined by the layout class.
	 *
	 * @return string The HTML for the layout, or `null` on error.
	 */
	public abstract function html(): string;
}
