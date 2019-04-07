<?php

/**
 * Defines the VerticalLayout class.
 * 
 * Applications that wish to lay out forms using vertical boxes must include this file.
 * 
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/Layout.php
 * - classes/equit/LibEquit\PageElement.php
 * - classes/equit/LibEquit\Page.php
 *
 * ### Changes
 * - (2017-05) Updated documentation. Migrated to `[]` syntax for array literals.
 * - (2013-12-22) class created.
 *
 * @file VerticalLayout.php
 * @author Darren Edale
 * @date Jan 2018
 * @version 1.1.2
 * @package libequit
 */

namespace Equit\Html;

	use Equit\Html\Layout;
	use Equit\Html\PageElement;

/**
 * A layout based on a single vertical column.
 *
 * The VerticalLayout class organises elements and layouts in a one-dimensional vertical column. Vertical layouts do
 * not support more than one element or layout per position in the column. The elements and layouts are rendered top-to-
 * bottom (i.e. element 0 is at the top, element _n-1_ is at the bottom).
 *
 * If an element or layout is inserted to any position that is occupied by an existing child  element or layout the
 * existing child is moved down and the new element or layout occupies its original position.
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
 * @class VerticalLayout
 * @author Darren Edale
 * @date Jan 2018
 * @version 1.1.2
 * @package libequit
 */
class VerticalLayout extends Layout {
	/** @var array The contents of the layout. */
	private $m_children = [];

	/** @var array A cache of the LibEquit\PageElement items in the layout. */
	private $m_elementCache = [];

	/** @var array A cache of the Layout items in the layout. */
	private $m_layoutCache = [];

	/**
	 * Create a new vertical layout.
	 *
	 * By default a vertical layout with no ID is created.
	 *
	 * @param $id string _optional_ The ID for the element.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Add a form element to the end of the vertical layout.
	 *
	 * The element is added after the last current child.
	 *
	 * @param $element PageElement is the element to add.
	 *
	 * @return bool _true_ if the element was added, _false_ otherwise.
	 */
	public function addElement(PageElement $element): bool {
		return $this->insertElement($element, $this->childCount());
	}

	/**
	 * Add a page element to an indexed position in the vertical layout.
	 *
	 * Indices start at 0 for the topmost child. If the index is 0 or less the form element is inserted as the first
	 * child in the layout; if it is equal to or greater than the current child count, it is added as the last child
	 * in the layout. If the index is already occupied, the existing child and all children below it are shifted one
	 * position down and the new form element occupies the vacated index.
	 *
	 * @param $element PageElement is the form element to add.
	 * @param $i int _optional_ is the index at which to insert the form element.
	 *
	 * @return bool _true_ if the form element was inserted, _false_ otherwise.
	 */
	public function insertElement(PageElement $element, int $i = 0): bool {
		$s = $this->childCount();

		if($i < 0) {
			$i = 0;
		} else {
			if($i > $s) {
				$i = $s;
			}
		}

		/* TODO use array_splice() instead? */
		if($i < $s) {
			for($j = $s - 1; $j >= $i; --$j) {
				$this->m_children[$j + 1] = $this->m_children[$j];
			}
		}

		$this->m_children[$i]   = $element;
		$this->m_elementCache[] = $element;
		return true;
	}

	/**
	 * Add a layout to the end of the vertical layout.
	 *
	 * The layout is added after the last current child.
	 *
	 * @param $layout Layout is the layout to add.
	 *
	 * @return bool _true_ if the layout was added, _false_ otherwise.
	 */
	public function addLayout(Layout $layout): bool {
		return $this->insertLayout($layout, $this->childCount());
	}

	/**
	 * Add a layout to an indexed position in the vertical layout.
	 *
	 * Indices start at 0 for the topmost child. If the index is 0 or less
	 * the layout is inserted as the first child in this layout; if it is equal
	 * to or greater than the current child count, it is added as the last
	 * child in this layout. If the index is already occupied, the existing
	 * child and all children to below it are shifted one position down and the
	 * new layout occupies the vacated index.
	 *
	 * @param $layout Layout is the layout to add.
	 * @param $i int _optional_ is the index at which to insert the layout.
	 *
	 * @return bool _true_ if the layout was inserted, _false_ otherwise.
	 */
	public function insertLayout(Layout $layout, int $i = 0): bool {
		$s = $this->childCount();

		if($i < 0) {
			$i = 0;
		} else {
			if($i > $s) {
				$i = $s;
			}
		}

		/* TODO use array_splice()? */
		if($i < $s) {
			for($j = $s - 1; $j >= $i; --$j) {
				$this->m_children[$j + 1] = $this->m_children[$j];
			}
		}

		$this->m_children[$i]  = $layout;
		$this->m_layoutCache[] = $layout;
		return true;
	}

	/**
	 * Get all the elements in the layout.
	 *
	 * The returned array contains just the form elements (i.e. not the child layouts). The order of the elements in the
	 * returned array is arbitrary. It is usually the order in which they were added. It cannot be assumed that elements
	 * will appear in the layout in the order in which they appear in the returned array.
	 *
	 * @return array[\LibEquit\PageElement] the child elements of the layout.
	 */
	public function elements(): array {
		return $this->m_elementCache;
	}

	/**
	 * Get all the layouts that are children of this layout.
	 *
	 * The returned array contains just the child layouts (i.e. not the form elements). The order of the layouts in the
	 * returned array is arbitrary. It is usually the order in which they were added. It cannot be assumed that child
	 * layouts will appear in this layout in the order in which they appear in the returned array.
	 *
	 * @return array[\Layout] the child layouts of the layout.
	 */
	public function layouts(): array {
		return $this->m_layoutCache;
	}

	/**
	 * Get all the children of this layout.
	 *
	 * The returned array contains all the form elements and layouts that are children of this layout, in the order in
	 * which they will appear in the layout.
	 *
	 * @return array[\Layout|\LibEquit\PageElement] the children of the layout.
	 */
	public function children(): array {
		return $this->m_children;
	}

	/**
	 * Counts the number of form elements in the layout.
	 *
	 * @return int The number of child elements.
	 */
	public function elementCount(): int {
		return count($this->m_elementCache);
	}

	/**
	 * Counts the number of child layouts in this layout.
	 *
	 * @return int The number of child layouts.
	 */
	public function layoutCount(): int {
		return count($this->m_layoutCache);
	}

	/**
	 * Counts the form elements and child layouts in this layout.
	 *
	 * @return int The number of children of any type.
	 */
	public function childCount(): int {
		return count($this->m_children);
	}

	/**
	 * Generate the HTML for the layout.
	 *
	 * The HTML produced is XHTML5 encoded as UTF-8.
	 *
	 * @return string the HTML.
	 */
	public function html(): string {
		if($this->childCount() < 1) {
			return "";
		}

		$class = $this->classNamesString();
		$id    = $this->id();
		$ret   = "<div " . (!empty($id) ? "id=\"" . html($id) . "\" " : "") . "class=\"verticallayout" . (!empty($class) ? " " . html($class) : "") . "\">";

		foreach($this->children() as $e) {
			$ret .= "<div class=\"verticallayout_cell\">" . $e->html() . "</div>";
		}

		$ret .= "</div>";
		return $ret;
	}
}
