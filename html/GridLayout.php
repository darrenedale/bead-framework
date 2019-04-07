<?php

/**
 * Defines the GridLayout class.
 *
 * ### Dependencies
 * - classes/equit/Layout.php
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\PageElement.php
 *
 * ### Changes
 * - (2018-10) Wrapped in LibEquit namespace. PHP7.2 type hinting.
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
 * - (2013-12-22) class ported from bpLibrary.
 *
 * @file GridLayout.php
 * @author Darren Edale
 * @version 1.1.2
 * @date Jan 2018
 * @package libequit
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\Detail\GridLayoutItem;
use Equit\Html\Layout;
use Equit\Html\PageElement;

/**
 * A HTML layout based on a two-dimensional grid.
 *
 * The grid layout class organises elements and layouts in a two-dimensional grid of cells. Grid layouts do not support
 * more than one element or layout per cell.
 *
 * Items in the layout are anchored in a specific cell but may span more than one cell. The anchor for any item is
 * always considered the top-left cell that it occupies - therefore all items span to the right and down the grid. If an
 * element or layout is added to any cell that is occupied by an existing child element or layout, whether that cell is
 * its anchor cell or simply one it spans, the existing child is removed and replaced with the new element or layout.
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
 * @class GridLayout
 * @author Darren Edale
 * @package libequit
 */
class GridLayout extends Layout {
	/** @var int Alignment flag to align content with the left edge of its cell. */
	public const AlignLeft = 0x01;

	/** @var int Alignment flag to align content with the horizontal centre of its cell. */
	public const AlignHCentre = 0x02;

	/** @var int Alignment flag to align content with the horizontal centre of its cell.
	 *
	 * This is an alias of _AlignHCentre_.
	 */
	public const AlignHCenter = self::AlignHCentre;

	/** @var int Alignment flag to align content with the horizontal centre of its cell.
	 *
	 * This is an alias of _AlignHCentre_.
	 *
	 * @deprecated Use AlignHCentre instead.
	 */
	public const AlignCentre = self::AlignHCentre;

	/** @var int Alignment flag to align content with the horizontal centre of its cell.
	 *
	 * This is an alias of _AlignHCentre_.
	 *
	 * @deprecated Use AlignHCentre instead.
	 */
	public const AlignCenter = self::AlignHCentre;

	/** @var int Alignment flag to align content with the right edge of its cell. */
	public const AlignRight = 0x04;

	/** @var int Alignment flag to align content with the top edge of its cell. */
	public const AlignTop = 0x10;

	/** @var int Alignment flag to align content with the vertical centre of its cell. */
	public const AlignVCentre = 0x20;

	/** @var int Alignment flag to align content with the vertical centre of its cell.
	 *
	 * This is an alias of _AlignVCentre_.
	 */
	public const AlignVCenter = self::AlignVCentre;

	/** @var int Alignment flag to align content with the vertical centre of its cell.
	 *
	 * This is an alias of _AlignVCentre_.
	 *
	 * @deprecated Use AlignVCentre instead.
	 */
	public const AlignMiddle = self::AlignVCentre;

	/** @var int Alignment flag to align content with the bottom edge of its cell. */
	public const AlignBottom = 0x40;

	/** @var array The child items in the layout.
	 *
	 * This is a 2D array of GridLayoutItems in the correct configuration for the layout.
	 */
	private $m_children = [];

	/** @var array A cache of all child items. */
	private $m_childCache = [];

	/** @var array A cache of just LibEquit\PageElement children. */
	private $m_elementCache = [];

	/** @var array A cache of just Layout children. */
	private $m_layoutCache = [];

	/** @var int The number of rows in the layout. */
	private $m_rowCount = 0;

	/** @var int The number of columns in the layout. */
	private $m_colCount = 0;

	/** @var bool Flag indicating that the cache arrays need to be repopulated. */
	private $m_cacheOutOfSync = false;

	/**
	 * Create a new GridLayout.
	 *
	 * By default, a layout with no ID is created.
	 *
	 * @param $id string _optional_ The ID for the layout.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Rebuilds the caches of elements, layouts and children.
	 *
	 * The *layouts()*, *elements()* and *children()* methods provide a flat array of items whereas internally the
	 * grid layout maintains a two-dimensional array of its children. The lists of child layouts, elements and
	 * children are cached so that they need not be rebuilt on every call to *elements()* *layouts()* or
	 *children()*. Those accessor methods check a flag that indicates when the caches need to be rebuilt and call
	 * this method to have that done. The *addElement()* and *addLayout()* methods set the flag when they detect
	 * that the caches contain children that are no longer in the layout.
	 */
	protected function rebuildCaches(): void {
		$this->m_layoutCache = [];
		$this->m_elementCache = [];
		$this->m_childCache = [];

		foreach($this->m_children as $itemRow) {
			if(!is_array($itemRow)) {
				continue;
			}

			foreach($itemRow as $item) {
				$this->m_childCache[] = $item->content;

				if($item->content instanceof Layout) {
					$this->m_layoutCache[] = $item->content;
				}
				else if($item->content instanceof PageElement) {
					$this->m_elementCache[] = $item->content;
				}
			}
		}

		$this->m_cacheOutOfSync = false;
	}

	/**
	 * Add an item to the grid layout.
	 *
	 * @param $item PageElement|Layout The item to add.
	 * @param $row `int` is the row at which to place the element.
	 * @param $col int is the column at which to place the element.
	 * @param $rowSpan int is the number of rows over which the element spans.
	 * @param $colSpan int is the number of columns over which the element
	 * spans.
	 * @param $alignment int _optional_ The alignment for the item.
	 *
	 * This is a helper method for addElement() and addLayout() that effectively
	 * is abstracted to ensure that the list of children is managed consistently
	 * in terms of detecting when an element or layout overlaps another and
	 * forces its removal from the layout.
	 *
	 * The alignment must be one of the class alignment constants.
	 *
	 * @return bool _true_ if the item was added, _false_ if not.
	 */
	protected function addItem( $item, int $row, int $col, int $rowSpan, int $colSpan, int $alignment = 0 ) {
		$itemIsLayout = ($item instanceof Layout);

		if(!$itemIsLayout && !($item instanceof PageElement)) {
			AppLog::error("invalid item", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(0 > $row || 0 > $col) {
			AppLog::error("invalid cell index", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(1 > $rowSpan || 1 > $colSpan) {
			AppLog::error("invalid cell span", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$myItem = $this->itemAt($row, $col);

		if($myItem instanceof GridLayoutItem) {
			unset($this->m_children[$row][$col]);
			$this->m_cacheOutOfSync = true;
		}

		if(!isset($this->m_children[$row]) || !is_array($this->m_children[$row])) {
			$this->m_children[$row] = [];
		}

		$this->m_children[$row][$col] = new GridLayoutItem($item, $row, $col, $rowSpan, $colSpan);
		$this->m_children[$row][$col]->alignment = $alignment;

		/* if cache is out of sync it means one or more caches contains an item
		   that is no longer a child of the layout. this can be because item
		   being added now has replaced an item or because a previous addition
		   replaced an item and the caches have not been rebuilt since; if cache
		   is not out of sync it means there are no redundant items in any of
		   the caches and that therefore simply adding the new item to the end
		   keeps the caches in sync */
		if(!$this->m_cacheOutOfSync) {
			$this->m_childCache[] = $item;

			if($itemIsLayout) {
				$this->m_layoutCache[] = $item;
			}
			else {
				$this->m_elementCache[] = $item;
			}
		}

		if(($row + $rowSpan) > $this->m_rowCount) {
			$this->m_rowCount = $row + $rowSpan;
		}

		if(($col + $colSpan) > $this->m_colCount) {
			$this->m_colCount = $col + $colSpan;
		}

		return true;
	}


	/**
	 * Add an element to the grid layout.
	 *
	 * The row and column parameters start at 0 for the top-left cell. Using values less than 0 will result in
	 * failure to add the element. Adding an element to a cell that already contains another layout or element will
	 * remove the other layout or element from the grid layout. The exception is when the other layout or element
	 * spans multiple cells and you add an element to a cell that is covered by the other layout or element but is
	 * not the top-left cell in its span. In such cases, the new element will be added to the grid layout in
	 * addition to the existing layout or element but will not be generated by html() because it will be masked by
	 * the existing layout or element.
	 *
	 * The alignment must be one of the class alignment constants.
	 *
	 * @param $element PageElement is the element to add.
	 * @param $row int The row at which to place the layout.
	 * @param $col int The column at which to place the layout.
	 * @param $rowSpan int The number of rows over which the layout spans. The default is 1 row.
	 * @param $colSpan int The number of columns over which the layout spans. The default is 1 column.
	 * @param $alignment int The alignment for the layout.
	 *
	 * @return bool _true_ if the element was added to the grid, _false_ otherwise.
	 */
	public function addElement(PageElement $element, int $row = 0, int $col = 0, int $rowSpan = 1, int $colSpan = 1, int $alignment = 0 ) {
		return $this->addItem($element, $row, $col, $rowSpan, $colSpan, $alignment);
	}


	/**
	 * Add a child layout to the grid layout.
	 *
	 * The row and column parameters start at 0 for the top-left cell. Using values less than 0 will result in
	 * failure to add the layout. Adding a layout to a cell that already contains another layout or element will
	 * remove the other layout or element from the grid layout. The exception is when the other layout or element
	 * spans multiple cells and you add a layout to a cell that is covered by the other layout or element but is not
	 * the top-left cell in its span. In such cases, the new layout will be added to the grid layout in addition to
	 * the existing layout or element but will not be generated by html() because it will be masked by the existing
	 * layout or element.
	 *
	 * The alignment must be one of the class alignment constants.
	 *
	 * @param $layout Layout The layout to add.
	 * @param $row int The row at which to place the layout.
	 * @param $col int The column at which to place the layout.
	 * @param $rowSpan int The number of rows over which the layout spans. The default is 1 row.
	 * @param $colSpan int The number of columns over which the layout spans. The default is 1 column.
	 * @param $alignment int The alignment for the layout.
	 *
	 * @return bool _true_ if the layout was added to the grid, _false_ otherwise.
	 */
	public function addLayout(Layout $layout, int $row = 0, int $col = 0, int $rowSpan = 1, int $colSpan = 1, int $alignment = 0 ): bool {
		return $this->addItem($layout, $row, $col, $rowSpan, $colSpan, $alignment);
	}

	/**
	 * Retrieve all the form elements contained in the grid layout.
	 *
	 * The elements appear in the array in no particular order and the order in which they appear may not even be
	 * consistent between calls to this method.
	 *
	 * @return array[LibEquit\PageElement] all the elements in the layout.
	 */
	public function elements(): array {
		if($this->m_cacheOutOfSync) {
			$this->rebuildCaches();
		}

		return $this->m_elementCache;
	}

	/**
	 * Retrieve all the layouts contained in the grid layout.
	 *
	 * The layouts appear in the array in no particular order and the order in which they appear may not even be
	 * consistent between calls to this method.
	 *
	 * @return array[Layout] all the layouts in the grid layout.
	 */
	public function layouts(): array {
		if($this->m_cacheOutOfSync) {
			$this->rebuildCaches();
		}

		return $this->m_layoutCache;
	}

	/**
	 * Retrieve all the children contained in the grid layout.
	 *
	 * The elements and layouts appear in the array in no particular order and the order in which they appear may
	 * not even be consistent between calls to this method.
	 *
	 * @return array[Layout|LibEquit\PageElement] all the children in the grid layout.
	 */
	public function children(): array {
		if($this->m_cacheOutOfSync) {
			$this->rebuildCaches();
		}

		return $this->m_childCache;
	}

	/**
	 * Counts the number of items in the layout.
	 *
	 * The count includes both layout and element children of the grid layout.
	 *
	 * @return int the number of items in the layout.
	 */
	public function childCount(): int {
		if($this->m_cacheOutOfSync) {
			$this->rebuildCaches();
		}

		return count($this->m_childCache);
	}

	/**
	 * Counts the number of form elements in the grid layout.
	 *
	 * The count includes _PageElement_ objects that are children of the grid layout (i.e. anything successfully
	 * added with _addElement()_ that remains in the layout).
	 *
	 * @return int the number of form elements in the layout.
	 */
	public function elementCount(): int {
		if($this->m_cacheOutOfSync) {
			$this->rebuildCaches();
		}

		return count($this->m_elementCache);
	}

	/**
	 * Counts the number of child layouts in the grid layout.
	 *
	 * @return int the number of child layouts in the grid layout.
	 */
	public function layoutCount(): int {
		if($this->m_cacheOutOfSync) {
			$this->rebuildCaches();
		}

		return count($this->m_layoutCache);
	}

	/**
	 * Counts the number of rows in the grid layout.
	 *
	 * The count accommodates the span of items that cover more than one row.
	 *
	 * @return int the number of rows in the layout.
	 */
	public function rowCount(): int {
		return $this->m_rowCount;
	}

	/**
	 * Counts the number of columns in the grid layout.
	 *
	 * The count accommodates the span of items that cover more than one column.
	 *
	 * @return int the number of columns in the layout.
	 */
	public function columnCount(): int {
		return $this->m_colCount;
	}

	/**
	 * Retrieve the element in a specified cell.
	 *
	 * @param $row int is the row from which the element is sought.
	 * @param $col int is the column from which the element is sought.
	 *
	 * @return PageElement|null the element at the cell index, or _null_ if the cell does not contain an element, is
	 * not valid or an error occurred.
	 */
	public function elementAt(int $row, int $col): ?PageElement {
		if(!is_int($row) || !is_int($col) || $row < 0 || $col < 0) {
			AppLog::error('invalid cell index', __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$item = $this->itemAt($row, $col);

		if($item instanceof GridLayoutItem && $item->content instanceof PageElement) {
			return $item->content;
		}

		return null;
	}

	/**
	 * Retrieve the layout in a specified cell.
	 *
	 * @param $row int is the row from which the layout is sought.
	 * @param $col int is the column from which the layout is sought.
	 *
	 * @return Layout|null the layout at the cell index, or _null_ if the cell does not contain a layout, is not
	 * valid or an error occurred.
	 */
	public function layoutAt(int $row, int $col): ?Layout {
		if(!is_int($row) || !is_int($col) || $row < 0 || $col < 0) {
			AppLog::error('invalid cell index', __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$item = $this->itemAt($row, $col);

		if($item instanceof GridLayoutItem && $item->content instanceof PageElement) {
			return $item->content;
		}

		return null;
	}

	/**
	 * Retrieve the layout or element in a specified cell.
	 *
	 * @param $row int is the row from which the layout or element is sought.
	 * @param $col int is the column from which the layout or element is sought.
	 *
	 * @return PageElement|Layout|null the layout at the cell index, or _null_ if the cell index is empty, not valid
	 * or an error occurred.
	 */
	public function childAt(int $row, int $col) {
		if(0 > $row || 0 > $col) {
			AppLog::error("invalid cell index", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$item = $this->itemAt($row, $col);

		if($item instanceof GridLayoutItem) {
			return $item->content;
		}

		return null;
	}

	/**
	 * Retrieve the item in a specified cell.
	 *
	 * @param $row int is the row from which the item is sought.
	 * @param $col int is the column from which the item is sought.
	 *
	 * @return GridLayoutItem|null the item at the cell index, or _null_ if the cell index is
	 * empty, is not valid or an error occurred.
	 */
	protected function itemAt(int $row, int $col): ?GridLayoutItem {
		if(0 > $row || 0 > $col) {
			AppLog::error("invalid cell index", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		foreach($this->m_children as $itemRow) {
			foreach($itemRow as $item) {
				if($row >= $item->anchorRow && $col >= $item->anchorCol && $row < ($item->anchorRow + $item->rowSpan) && $col < ($item->anchorCol + $item->colSpan)) {
					return $item;
				}
			}
		}

		return null;
	}

	/**
	 * Retrieve the item in a specified cell.
	 *
	 * If the cell does not contain an item this method returns _null_.
	 *
	 * @param $row int is the row from which the item is sought.
	 * @param $col int is the column from which the item is sought.
	 *
	 * @return GridLayoutItem|null the item at the cell index, or _null_ if the cell index is empty, not
	 * valid, or an error occurred.
	 */
	protected function itemAnchoredAt(int $row, int $col): ?GridLayoutItem {
		if(0 > $row || 0 > $col) {
			AppLog::error("invalid cell index", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		if(isset($this->m_children[$row]) && isset($this->m_children[$row][$col])) {
			return $this->m_children[$row][$col];
		}

		return null;
	}

	/**
	 * Generate the HTML for the layout.
	 *
	 * A HTML table is used to generate the grid for the layout. The HTML is XHTML5, UTF-8 encoded.
	 *
	 * @return string The HTML for the layout.
	 */
	public function html(): string {
		if($this->elementCount() < 1) {
			return "";
		}

		$class = $this->classNamesString();
		$id = $this->id();
		$ret = "<table" . (!empty($id) ? " id=\"" . html($id) . "\"" : "") . " class=\"layout" . (!empty($class) ? " " . html($class) : "") . "\"><tbody>";

		for($row = 0; $row < $this->rowCount(); $row++) {
			$ret .= "<tr>";

			for($col = 0; $col < $this->columnCount(); $col++) {
				$item = $this->itemAt($row, $col);

				if(!($item instanceof GridLayoutItem)) {
					$ret .= "<td class=\"gridlayout_cell\"></td>";
					continue;
				}

				// don't generate a layout cell for an item anchored elsewhere that spans this cell
				if($item->anchorRow < $row || $item->anchorCol < $col) {
					continue;
				}

				$ret .= "<td";

				if($item->rowSpan > 1) {
					$ret .= " rowspan=\"{$item->rowSpan}\"";
				}

				if($item->colSpan > 1) {
					$ret .= " colspan=\"{$item->colSpan}\"";
				}

				$alignClass = "";

				if($item->alignment & self::AlignLeft) {
					$alignClass .= " content-align-left";
				}
				else if($item->alignment & self::AlignHCentre) {
					$alignClass .= " content-align-center content-align-centre";
				}
				else if($item->alignment & self::AlignRight) {
					$alignClass .= " content-align-right";
				}

				if($item->alignment & self::AlignTop) {
					$alignClass .= " content-align-top";
				}
				else if($item->alignment & self::AlignVCentre) {
					$alignClass .= " content-align-middle;";
				}
				else if($item->alignment & self::AlignBottom) {
					$alignClass .= " content-align-bottom;";
				}

				$ret .= " class=\"gridlayout_cell$alignClass\">";

				/* content can only be LibEquit\PageElement or Layout */
				$ret .= $item->content->html();
				$ret .= "</td>";
			}

			$ret .= "</tr>";
		}

		$ret .= "</tbody></table>";
		return $ret;
	}
}


namespace Equit\Html\Detail;

use Equit\Html\PageElement;
use Equit\Html\Layout;

/**
 * Represents a child item in a GridLayout.
 *
 * This is a private class and should not be used at all. It is used internally in GridLayout to contain items in
 * the layout along with their indices in the grid and the extent of their span. Its internals are not guaranteed to
 * remain consistent. If PHP supported nested classes, this class would be a private nested class of GridLayout.
 *
 * Basically, just don't touch it.
 *
 * @internal
 *
 * @class GridLayoutItem
 * @author Darren Edale
 * @package libequit
 */
class GridLayoutItem {
	/** @var Layout|PageElement|null */
	public $content   = null;
	public $anchorRow = null;
	public $anchorCol = null;
	public $rowSpan   = 1;
	public $colSpan   = 1;
	public $alignment = 0;

	public function __construct($content, $anchorRow, $anchorCol, $rowSpan, $colSpan) {
		$this->content   = $content;
		$this->anchorRow = $anchorRow;
		$this->anchorCol = $anchorCol;
		$this->rowSpan   = $rowSpan;
		$this->colSpan   = $colSpan;
	}
}
