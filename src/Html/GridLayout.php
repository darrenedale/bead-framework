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
 * @version 0.9.1
 * @version 0.9.1 * @package libequit
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\Detail\GridLayoutItem;

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
	 * Rebuilds the cache of element..
	 *
	 * The *elements()* method provides a flat array of items whereas internally the grid layout maintains a
	 * two-dimensional array of its elements. The list of child elements is cached so that they it not be rebuilt on
	 * every call to *elements()*. This accessor method checks a flag that indicates when the cache needs to be rebuilt
	 * and calls this method to have that done. The *addElement()* and *insertElement()* methods set the flag when they
	 * detect that the cache contains elements that are no longer in the layout.
	 */
	protected function rebuildCache(): void {
		$this->m_elementCache = [];

		foreach($this->m_grid as $itemRow) {
			if(!is_array($itemRow)) {
				continue;
			}

			foreach($itemRow as $item) {
				$this->m_elementCache[] = $item->content;
			}
		}

		$this->m_cacheOutOfSync = false;
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
		public function addElement(PageElement $element, int $row = 0, int $col = 0, int $rowSpan = 1, int $colSpan = 1, int $alignment = 0 ): bool {
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
			unset($this->m_grid[$row][$col]);
			$this->m_cacheOutOfSync = true;
		}

		if(!isset($this->m_grid[$row]) || !is_array($this->m_grid[$row])) {
			$this->m_grid[$row] = [];
		}

		$this->m_grid[$row][$col]            = new GridLayoutItem($element, $row, $col, $rowSpan, $colSpan);
		$this->m_grid[$row][$col]->alignment = $alignment;

		// if cache is out of sync it means it contains an item that is no longer a child of the layout. this can be
		// because item being added has replaced an item or because a previous addition replaced an item and the cache
		// has not been rebuilt since; if cache is not out of sync it means there are no redundant items in the cache
		// and that therefore simply adding the new item to the end keeps the cache in sync
		if(!$this->m_cacheOutOfSync) {
			$this->m_elementCache[] = $element;
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
	 * Retrieve all the form elements contained in the grid layout.
	 *
	 * The elements appear in the array in no particular order and the order in which they appear may not even be
	 * consistent between calls to this method.
	 *
	 * @return array[LibEquit\PageElement] all the elements in the layout.
	 */
	public function elements(): array {
		if($this->m_cacheOutOfSync) {
			$this->rebuildCache();
		}

		return $this->m_elementCache;
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
			$this->rebuildCache();
		}

		return count($this->m_elementCache);
	}

	/**
	 * Clear all items out of the layout.
	 *
	 * After a call to this method the layout will be empty.
	 */
	public function clear(): void {
		$this->m_grid         = [];
		$this->m_elementCache = [];
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
		if($row < 0 || $col < 0) {
			AppLog::error('invalid cell index', __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		$item = $this->itemAt($row, $col);

		if(!$item) {
			return null;
		}

		return $item->content;
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

		foreach($this->m_grid as $itemRow) {
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

		if(isset($this->m_grid[$row]) && isset($this->m_grid[$row][$col])) {
			return $this->m_grid[$row][$col];
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
		$nRows = $this->rowCount();
		$nCols = $this->columnCount();

		for($row = 0; $row < $nRows; $row++) {
			$ret .= "<tr>";

			for($col = 0; $col < $nCols; $col++) {
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

	/** @var array The child items in the layout.
	 *
	 * This is a 2D array of GridLayoutItems in the correct configuration for the layout.
	 */
	private $m_grid = [];

	/** @var array A cache of just LibEquit\PageElement children. */
	private $m_elementCache = [];

	/** @var int The number of rows in the layout. */
	private $m_rowCount = 0;

	/** @var int The number of columns in the layout. */
	private $m_colCount = 0;

	/** @var bool Flag indicating that the cache arrays need to be repopulated. */
	private $m_cacheOutOfSync = false;
}
