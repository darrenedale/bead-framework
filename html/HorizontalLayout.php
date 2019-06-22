<?php

/**
* Defines the _HorizontalLayout_ class.
*
* Applications that wish to lay out forms using horizontal boxes must include
* this file.
*
* ### Dependencies
* - classes/equit/AppLog.php
* - classes/equit/Layout.php
* - classes/equit/LibEquit\PageElement.php
* - classes/equit/LibEquit\Page.php
*
* ### Changes
* - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
* - (2013-12-22) class created.
*
* @file HorizontalLayout.php
* @author Darren Edale
* @version 1.1.2
* @date Jan 2018
* @package libequit
*/

namespace Equit\Html;

/**
 * A HTML layout based on a single horizontal row.
 *
 * The Horizontal layout class organises elements and layouts in a one-dimensional horizontal line. Horizontal
 * layouts do not support more than one element or layout per position in the line.
 *
 * If an element or layout is inserted to any position that is occupied by an existing child  element or layout the
 * existing child is moved to the right and the new element or layout occupies its original position.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This module does not provide an API.
 *
 * ### Events
 * This module does not emit any events.
 *
 * ### Connections
 * This module does not connect to any events.
 *
 * ### Settings
 * This module does not read any settings.
 *
 * ### Session Data
 * This module does not create a session context.
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 *
 * @class HorizontalLayout
 * @author Darren Edale
 * @package libequit
 */
class HorizontalLayout extends Layout {
	/**
	 * Create a new HorizontalLayout.
	 *
	 * By default, a layout without an ID is creaed.
	 *
	 * @param $id string _optional_ The ID for the layout.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Add a page element to the end of the horizontal layout.
	 *
	 * The element is added after the last current child.
	 *
	 * @param $element PageElement is the element to add.
	 *
	 * @return bool true
	 */
	public function addElement(PageElement $element): bool {
		$this->insertElement($element, $this->elementCount());
		return true;
	}

	/**
	 * Add a page element to an indexed position in the horizontal layout.
	 *
	 * Indices start at 0 for the leftmost child. If the index is 0 or less the form element is inserted as the
	 * first child in the layout; if it is equal to or greater than the current child count, it is added as the last
	 * child in the layout. If the index is already occupied, the existing child and all children to its right are
	 * shifted one position to the right and the new form element occupies the vacated index.
	 *
	 * @param $element PageElement is the page element to add.
	 * @param $insertIndex int _optional_ is the index at which to insert the form element. The default is to insert
	 * the element at the beginning.
	 *
	 * @return bool true
	 */
	public function insertElement(PageElement $element, int $insertIndex = 0): bool {
		$elementCount = $this->elementCount();

		if($insertIndex < 0) {
			$insertIndex = 0;
		}
		else if($insertIndex > $elementCount) {
			$insertIndex = $elementCount;
		}

		if($insertIndex < $elementCount) {
			for($i = $elementCount - 1; $i >= $insertIndex; --$i) {
				$this->m_elements[$i + 1] = $this->m_elements[$i];
			}
		}

		$this->m_elements[$insertIndex] = $element;
		return true;
	}

	/**
	 * Get all the elements in the layout.
	 *
	 * The returned array contains just the form elements (i.e. not the child layouts). The order of the elements in
	 * the returned array is arbitrary. It is usually the order in which they were added. It cannot be assumed that
	 * elements will appear in the layout in the order in which they appear in the returned array.
	 *
	 * @return array[\LibEquit\PageElement] the child elements of the layout.
	 */
	public function elements(): array {
		return $this->m_elements;
	}

	/**
	 * Counts the number of form elements in the layout.
	 *
	 * @return int The number of child elements.
	 */
	public function elementCount(): int {
		return count($this->m_elements);
	}

	/**
	 * Clear all content from the layout.
	 *
	 * After a call to this method, the layout will be empty.
	 */
	public function clear(): void {
		$this->m_elements = [];
	}

	/**
	 * Generate the HTML for the layout.
	 *
	 * The HTML is XHTML5, UTF-8 encoded.
	 *
	 * @return string the HTML.
	 */
	public function html(): string {
		if($this->elementCount() < 1) {
			return "";
		}

		$class = $this->classNamesString();
		$id    = $this->id();
		$ret   = "<div " . (!empty($id) ? "id=\"" . html($id) . "\" " : "") . "class=\"horizontallayout" . (!empty($class) ? " " . html($class) : "") . "\">";

		foreach($this->elements() as $e) {
			$ret .= "<div class=\"horizontallayout_cell\">" . $e->html() . "</div>";
		}

		$ret .= "</div>";
		return $ret;
	}

	/** @var array The items in the layout. */
	private $m_elements = [];
}
