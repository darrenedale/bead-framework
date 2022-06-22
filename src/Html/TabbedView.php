<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 02/03/19
 * Time: 16:40
 */

namespace Equit\Html;

use Equit\AppLog;

/**
 * Class TabbedView
 *
 * @package LibEquit
 */
class TabbedView extends Element {
	use HasTooltip;

	/**
	 * @var array The tabs that have been added to the view.
	 */
	private $m_tabs = [];

	/**
	 * @var int The initial tab to select in the TabbedView.
	 */
	private $m_initialSelectedTabIndex = 0;

	/**
	 * Initialise a new TabbedView.
	 *
	 * @param string|null $id
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	/**
	 * Insert a tab into the view.
	 *
	 * If the index is <= 0, the tab is inserted as the first in the view; if it is >= the current number of tabs, it is
	 * inserted as the last.
	 *
	 * The label is required, and it must be a string or a PageElement. If you want an empty label, provide an empty
	 * string.
	 *
	 * @param int $idx
	 * @param string|Element $label
	 * @param Element $content
	 *
	 * @return bool `true` if the tab was inserted, `false` if not.
	 */
	public function insertTab(int $idx, $label, ?Element $content): bool {
		if(is_string($label)) {
			$label = new HtmlLiteral(html($label));
		}

		if(!$label instanceof Element) {
			AppLog::error("invalid label content - expecting string or PageElement", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$tab = (object) [
			"label" => $label,
			"content" => $content,
		];

		if($this->tabCount() <= $idx) {
			$this->m_tabs[] = $tab;
		}
		else if(0 >= $idx) {
			array_unshift($this->m_tabs, $tab);
		}
		else {
			array_splice($this->m_tabs, $idx, 0, [$tab]);
		}

		return true;
	}

	/**
	 * @param $label
	 * @param Element|null $content
	 *
	 * @return bool `true` if the tab was added, `false` if not.
	 */
	public function addTab($label, ?Element $content): bool {
		return $this->insertTab($this->tabCount(), $label, $content);
	}

	/**
	 * Remove a tab from the view.
	 *
	 * If $idx is < 0 or >= the current number of tabs, nothing happens.
	 *
	 * @param int $idx
	 */
	public function removeTab(int $idx) {
		if(0 > $idx || $this->tabCount() <= $idx) {
			return;
		}

		array_splice($this->m_tabs, $idx, 1);
	}

	public function setSelectedTab(int $idx): bool {
		if(0 > $idx || $this->tabCount() <= $idx) {
			return false;
		}

		$this->m_initialSelectedTabIndex = $idx;
		return true;
	}

	public function selectedTab(): int {
		return $this->m_initialSelectedTabIndex;
	}

	public function tabCount(): int {
		return count($this->m_tabs);
	}

	public function tabLabel(int $idx): ?Element {
		if(0 > $idx || $this->tabCount() <= $idx) {
			return null;
		}

		return $this->m_tabs[$idx]->label;
	}

	public function tabContent(int $idx): ?Element {
		if(0 > $idx || $this->tabCount() <= $idx) {
			return null;
		}

		return $this->m_tabs[$idx]->content;
	}

	/**
	 * Generate the HTML for the tab view.
	 *
	 * The HTML is structured as:
	 * <div class="eq-tabview>
	 *   <div class="eq-tabview-tabs">
	 *     <div class="eq-tabview-tab"></div>
	 *     <div class="eq-tabview-tab"></div>
	 *     ...
	 *   </div>
	 *   <div class="eq-tabview-content-container">
	 *     <div class="eq-tabview-content" style="display: none;"></div>
	 *     <div class="eq-tabview-content" style="display: none;"></div>
	 *     ...
	 *   </div>
	 * </div>
	 *
	 * The style attribute of each content div is manipulated as the user selects tabs.
	 *
	 * @return string
	 */
	public function html(): string {
		$classes = $this->classNames();
		$this->addClassName("eq-tabview");
		$ret = "<div {$this->emitAttributes()}><div class=\"eq-tabview-tabs\">";
		$this->setClassNames($classes);
		$i = 0;

		foreach($this->m_tabs as $tab) {
			$ret .= "<div class=\"eq-tabview-tab" . ($i == $this->m_initialSelectedTabIndex ? " selected" : "") .
				"\">{$tab->label->html()}</div>";
			++$i;
		}

		$ret .= "</div><div class=\"eq-tabview-content-container\">";
		$i = 0;

		foreach($this->m_tabs as $tab) {
			$ret .= "<div class=\"eq-tabview-content" . ($i == $this->m_initialSelectedTabIndex ? " selected" : "") .
				"\">{$tab->content->html()}</div>";
			++$i;
		}

		$ret .= "</div></div>";

		return $ret;
	}
}
